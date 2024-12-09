<?php

namespace App\Http\Controllers;

use App\Models\Video;
use FFI\Exception;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\Client\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\SsmlVoiceGender; // Firebase Factory for storage
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Http;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Kreait\Firebase\Factory;
use Log;
use SabatinoMasala\Replicate\Replicate;
use Str;

class VideoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Video/index', );
    }

    public function generateContent($input)
    {
        $apiKey = env('GROQ_API_KEY'); // Use the API key for GROQ
        $url = "https://api.groq.com/openai/v1/chat/completions"; // GROQ API URL

        Log::info('API URL:', [$input]);

        // Define tools for JSON validation and response shortening
        $tools = [
            [
                "type" => "function",
                "function" => [
                    "name" => "validate_json",
                    "description" => "Validates and fixes JSON structure to ensure it's a valid array.",
                    "parameters" => [
                        "type" => "object",
                        "properties" => [
                            "json_input" => [
                                "type" => "string",
                                "description" => "The raw JSON string to validate and fix.",
                            ],
                        ],
                        "required" => ["json_input"],
                    ],
                ],
            ],
            [
                "type" => "function",
                "function" => [
                    "name" => "shorten_response",
                    "description" => "Shortens a given text to ensure it fits within token constraints.",
                    "parameters" => [
                        "type" => "object",
                        "properties" => [
                            "text" => [
                                "type" => "string",
                                "description" => "The text content to shorten.",
                            ],
                        ],
                        "required" => ["text"],
                    ],
                ],
            ],
        ];

        // Create the message data for the GROQ API request
        $messages = [
            [
                'role' => 'system',
                'content' => "You are a content generation assistant. Use tools when necessary:
            1. If the JSON is malformed, use 'validate_json' before returning the final answer.
            2. If the descriptions risk exceeding token limits, draft first and then call 'shorten_response' with the overly long text before finalizing.

            All scenes, prompts, and text must be safe for all audiences.
            Do not produce NSFW content.
            Keep the response concise enough so it fits within the max token limit.

            Produce between 5 and 10 scenes. Do not exceed 10 scenes.
            Once you've produced the required scenes, end the JSON array immediately, with no extra text.

            If you risk exceeding token limits, shorten descriptions rather than producing extra scenes or leaving the JSON incomplete.",
            ],
            [
                'role' => 'user',
                'content' => "{$input}

            Requirements:
            1. Output a **single JSON array** of objects, each with:
               - `imagePrompt`: A vivid, imaginative scene description.
               - `contextText`: A creative story/narrative for that scene.
            2. Produce 5 to 10 scenes total, no more, no fewer.
            3. End the array after the required number of scenes. No extra text.
            4. The response must be the JSON array only, no introductions or notes.
            5. Validate the JSON using 'validate_json' if needed (no trailing commas, missing brackets, or keys).
            6. No NSFW content.
            7. Keep it within token constraints. If too long, call 'shorten_response' on overly long text first.
            ",
            ],
        ];

        $data = [
            'model' => 'llama3-groq-70b-8192-tool-use-preview', // Tool-use compatible model
            'messages' => $messages,
            'tools' => $tools,
            'tool_choice' => 'auto',
            'max_tokens' => 4096,
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => "Bearer {$apiKey}",
            ])->post($url, $data);

            // Log the raw response for debugging
            Log::info('API Raw Response:', [$response->body()]);

            $responseData = $response->json();

            // Check if the model decided to use a tool
            if (isset($responseData['choices'][0]['message']['tool_calls'])) {
                foreach ($responseData['choices'][0]['message']['tool_calls'] as $toolCall) {
                    $functionName = $toolCall['function']['name'];

                    if ($functionName === 'validate_json') {
                        $jsonInput = json_decode($toolCall['function']['arguments'], true)['json_input'];
                        $fixedJson = $this->validateJson($jsonInput);
                        Log::info('Fixed JSON:', [$fixedJson]);
                        return $fixedJson;
                    } elseif ($functionName === 'shorten_response') {
                        $textToShorten = json_decode($toolCall['function']['arguments'], true)['text'];
                        $shortened = $this->shortenResponse($textToShorten);

                        // After shortening, you may need to re-run the request or decide how to integrate this shortened text.
                        // For simplicity, just return the shortened version. In practice, you might need to incorporate this
                        // back into the conversation and re-request a final JSON array.
                        return $shortened;
                    }
                }
            }

            // If no tools were used, return the raw content
            $output = $responseData['choices'][0]['message']['content'];
            return $output;

        } catch (\Exception $e) {
            Log::error('Exception occurred:', [$e->getMessage()]);
            return ['error' => 'An unexpected error occurred.'];
        }
    }

/**
 * A helper function to manually validate and fix JSON if needed.
 */
    // private function validateJson($jsonInput)
    // {
    //     $decoded = json_decode($jsonInput);
    //     if (json_last_error() === JSON_ERROR_NONE) {
    //         return json_encode($decoded, JSON_PRETTY_PRINT);
    //     }

    //     // Attempt to remove trailing commas
    //     $fixed = preg_replace('/,\s*([\]}])/', '$1', $jsonInput);
    //     $decoded = json_decode($fixed);

    //     if (json_last_error() === JSON_ERROR_NONE) {
    //         return json_encode($decoded, JSON_PRETTY_PRINT);
    //     }

    //     Log::error('Failed to fix JSON:', [json_last_error_msg()]);
    //     return ['error' => 'Invalid JSON could not be fixed.'];
    // }

    private function validateJson($jsonInput)
    {
        $decoded = json_decode($jsonInput);
        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT);
        }

        // Attempt to fix common JSON issues
        $fixed = preg_replace('/,\s*([\]}])/', '$1', $jsonInput); // Remove trailing commas
        $fixed = preg_replace('/({\s*"imagePrompt":\s*"[^"]*")(\s*"contextText":)/', '$1, $2', $fixed); // Ensure comma between properties

        $decoded = json_decode($fixed);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT);
        }

        Log::error('Failed to fix JSON:', [json_last_error_msg()]);
        return ['error' => 'Invalid JSON could not be fixed.'];
    }

/**
 * A helper function to shorten text if necessary.
 */
    private function shortenResponse($text)
    {
        // Implement logic to shorten text. This is a naive example:
        $maxLength = 2000; // Arbitrary max length
        if (strlen($text) > $maxLength) {
            return substr($text, 0, $maxLength) . '...';
        }
        return $text;
    }

    public function generateVideoScript(Request $request)
    {
        $input = $request->input('input');
        $response = $this->generateContent($input);

        return response()->json($response);
    }

    private function uploadFile($api_key, $path)
    {
        $url = 'https://api.assemblyai.com/v2/upload';
        $data = file_get_contents($path);

        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-type: application/octet-stream\r\nAuthorization: $api_key",
                'content' => $data,
            ],
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);

        if ($http_response_header[0] == 'HTTP/1.1 200 OK') {
            $json = json_decode($response, true);
            return $json['upload_url'];
        } else {
            throw new Exception("Error: " . $http_response_header[0] . " - $response");
        }
    }

    /**
     * Create a transcript using AssemblyAI API.
     */
    private function createTranscript($api_key, $audio_url)
    {
        $url = "https://api.assemblyai.com/v2/transcript";

        $headers = [
            "authorization: " . $api_key,
            "content-type: application/json",
        ];

        $data = [
            "audio_url" => $audio_url,
        ];

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $response = json_decode(curl_exec($curl), true);
        curl_close($curl);

        $transcript_id = $response['id'];
        $polling_endpoint = "https://api.assemblyai.com/v2/transcript/" . $transcript_id;

        while (true) {
            $polling_response = curl_init($polling_endpoint);
            curl_setopt($polling_response, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($polling_response, CURLOPT_RETURNTRANSFER, true);

            $transcription_result = json_decode(curl_exec($polling_response), true);

            if ($transcription_result['status'] === "completed") {
                return $transcription_result;
            } else if ($transcription_result['status'] === "error") {
                throw new Exception("Transcription failed: " . $transcription_result['error']);
            } else {
                sleep(3);
            }
        }
    }

    public function generateAudioAndTranscript(Request $request)
    {
        $base64Credentials = env('GOOGLE_APPLICATION_CREDENTIALS_BASE64');

        if (!$base64Credentials) {
            return response()->json(['error' => 'Base64 credentials missing in .env'], 500);
        }

        // Decode Base64 credentials
        $decodedJson = base64_decode($base64Credentials);
        if (!$decodedJson) {
            return response()->json(['error' => 'Failed to decode Base64 credentials'], 500);
        }

        $credentials = json_decode($decodedJson, true);
        if (!$credentials) {
            return response()->json(['error' => 'Decoded JSON is invalid'], 500);
        }

        // Log credentials to ensure it's working
        Log::info('Decoded Credentials:', $credentials);

        $text = $request->input('text');
        $id = $request->input('id');

        try {
            // Initialize the Text-to-Speech client
            $textToSpeechClient = new TextToSpeechClient([
                'credentials' => $credentials, // Pass credentials as an array
            ]);

            // Set the text input
            $input = new SynthesisInput();
            $input->setText($text);

            // Select the voice parameters
            $voice = new VoiceSelectionParams();
            $voice->setLanguageCode('en-US');
            $voice->setSsmlGender(SsmlVoiceGender::FEMALE);

            // Set the audio configuration
            $audioConfig = new AudioConfig();
            $audioConfig->setAudioEncoding(AudioEncoding::MP3); // Use MP3 format

            // Call the Text-to-Speech API
            $synthesizeSpeechRequest = new \Google\Cloud\TextToSpeech\V1\SynthesizeSpeechRequest();
            $synthesizeSpeechRequest->setInput($input);
            $synthesizeSpeechRequest->setVoice($voice);
            $synthesizeSpeechRequest->setAudioConfig($audioConfig);

            $resp = $textToSpeechClient->synthesizeSpeech($synthesizeSpeechRequest);

            // Save the synthesized audio to a temporary file
            $audioFilePath = storage_path('app/public/test.mp3');
            file_put_contents($audioFilePath, $resp->getAudioContent());

            // Initialize Firebase with credentials as an array
            $firebase = (new Factory())
                ->withServiceAccount($credentials)
                ->withDatabaseUri('https://shortsai-b68d2-default-rtdb.europe-west1.firebasedatabase.app/');

            $storage = $firebase->createStorage();
            $bucket = $storage->getBucket();

            $firebaseFilePath = 'audios/test_' . $id . '.mp3';
            $file = fopen($audioFilePath, 'r');
            $bucket->upload($file, [
                'name' => $firebaseFilePath,
                'metadata' => [
                    'contentType' => 'audio/mpeg',
                ],
            ]);

            // Make the uploaded object publicly accessible
            $fileReference = $bucket->object($firebaseFilePath);
            $fileReference->update([
                'acl' => [],
            ]);
            $fileReference->acl()->add('allUsers', 'READER');

            // Generate the public URL
            $publicUrl = "https://storage.googleapis.com/{$bucket->name()}/{$firebaseFilePath}";

            // Store the file URL in Firebase Database
            $database = $firebase->createDatabase();
            $database->getReference('audio_files/' . $id)->set([
                'url' => $publicUrl,
                'created_at' => now(),
            ]);

            // Call AssemblyAI for transcription
            $assemblyApiKey = env('ASSEMBLYAI_API_KEY');
            $uploadUrl = $this->uploadFile($assemblyApiKey, $audioFilePath);
            $transcript = $this->createTranscript($assemblyApiKey, $uploadUrl);

            return response()->json([
                'message' => 'Audio generated, uploaded, and transcribed successfully.',
                'url' => $publicUrl,
                'transcript' => $transcript['words'] ?? 'No transcript available.',
            ], 200);

        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } finally {
            if (isset($textToSpeechClient)) {
                $textToSpeechClient->close();
            }
        }
    }

    public function generateImages(Request $request)
    {
        $replicateApiToken = env('REPLICATE_API_TOKEN');
        $firebaseBase64Credentials = env('FIREBASE_CREDENTIALS_BASE64');

        if (!$firebaseBase64Credentials) {
            return response()->json(['error' => 'Firebase credentials are not configured properly.'], 500);
        }

        // Decode Base64 credentials
        $decodedCredentials = json_decode(base64_decode($firebaseBase64Credentials), true);
        if (!$decodedCredentials) {
            return response()->json(['error' => 'Failed to decode Firebase credentials.'], 500);
        }

        try {
            // Initialize the Replicate client
            $client = new Replicate($replicateApiToken);

            // Validate and retrieve the prompt from the request
            $prompt = $request->input('prompt');

            // Call Replicate API to generate the image
            $output = $client->run(
                'bytedance/sdxl-lightning-4step:5599ed30703defd1d160a25a63321b4dec97101d98b4674bcc56e41f62f35637',
                [
                    'input' => [
                        'prompt' => $prompt,
                        'width' => 1024,
                        'height' => 1280,
                        'num_outputs' => 1,
                        'safety_checker' => 'None',
                    ],
                    'webhook' => 'https://webhook.site/your-webhook-url',
                ]
            );

            $imageUrl = $output[0]; // Assume the first result is the desired image URL
            $imageContent = file_get_contents($imageUrl);

            // Initialize Firebase Storage with credentials as an array
            $firebase = (new Factory())
                ->withServiceAccount($decodedCredentials)
                ->withProjectId(env('PROJECT_ID'));

            $storage = $firebase->createStorage();
            $bucket = $storage->getBucket();

            // Generate a unique filename
            $filename = 'images/' . Str::uuid() . '.png';

            // Upload the PNG image to Firebase Storage
            $bucket->upload($imageContent, [
                'name' => $filename,
                'metadata' => [
                    'contentType' => 'image/png',
                ],
            ]);

            // Make the uploaded object publicly accessible
            $imageReference = $bucket->object($filename);
            $imageReference->update([
                'acl' => [],
            ]);
            $imageReference->acl()->add('allUsers', 'READER');

            // Generate the public URL
            $publicUrl = "https://storage.googleapis.com/{$bucket->name()}/{$filename}";

            // Return the public URL as JSON response
            return response()->json([
                'result' => $publicUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating image: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while generating the image.'], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function generateVideo(Request $request)
    {
        try {

            // Decode the incoming JSON data from the request
            $videoData = json_encode($request->input('videoData'), true);

            // Validate the decoded data
            if (!$videoData) {
                return response()->json(['message' => 'Invalid JSON data'], 422);
            }
            // Validate the incoming request
            $request->validate([
                'videoAudio' => 'required|string',
                'videoImages' => 'required|array',
                'videoImages.*' => 'url',
                'videoScript' => 'required|array',
                'videoScript.*.imagePrompt' => 'string',
                'videoScript.*.contextText' => 'string',
                'videoTranscript' => 'required|array',
                'videoTranscript.*.text' => 'required|string',
                'videoTranscript.*.start' => 'required|numeric',
                'videoTranscript.*.end' => 'required|numeric',
                'videoTranscript.*.confidence' => 'required|numeric',

            ]);

            // Extract validated data
            $videoData = $request->only([
                'videoAudio', 'videoImages', 'videoScript', 'videoTranscript',
            ]);

            // Save video to the database
            $video = Video::create([
                'videoAudio' => $videoData['videoAudio'],
                'videoImages' => $videoData['videoImages'],
                'videoScript' => $videoData['videoScript'],
                'videoTranscript' => $videoData['videoTranscript'],
                'user_id' => auth()->user()->id,
            ]);

            return response()->json(['message' => 'Video saved successfully', 'video' => $video], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            Log::error('Error saving video data', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error saving video data', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function getVideo(Request $request)
    {
        $video = Video::find($request->query('id'));

        if (!$video) {
            return response()->json(['error' => 'Video not found'], 404);
        }

        return response()->json(['video' => $video], 200);
    }

}
