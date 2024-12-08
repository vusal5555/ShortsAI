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

        // Define the tool for JSON validation and sanitization
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
        ];

        // Create the message data for the GROQ API request
        $messages = [
            [
                'role' => 'system',
                'content' => "You are a content generation assistant. Use tools when necessary to validate and fix JSON output.  Follow the user's instructions and DO NOT produce any NSFW content. Make sure you do not exceed max_tokens of 4096.
                All scenes, prompts, and text must be safe for all audiences.",
            ],
            [
                'role' => 'user',
                'content' => "{$input}

            Your response must adhere to the following requirements:
            1. Output a **single JSON array** of objects, each containing:
                - `imagePrompt`: A vivid and imaginative description of a scene.
                - `contextText`: A creative story or narrative related to the scene.
            2. The response **must only be the JSON array** itself. Do not:
                - Wrap the array in an additional array.
                - Add any introductory or explanatory text, such as 'Here is your JSON response.'
                - Include any notes, comments, or explanations.
            3. Validate the JSON using the 'validate_json' tool if needed. The JSON must be valid with no trailing commas, missing brackets, or keys.",
            ],
        ];

        $data = [
            'model' => 'llama3-groq-70b-8192-tool-use-preview', // Tool-use compatible model
            'messages' => $messages,
            'tools' => $tools, // Include tools for the model to use
            'tool_choice' => 'auto', // Let the model decide whether to use tools
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
                    if ($toolCall['function']['name'] === 'validate_json') {
                        // Extract arguments and call the tool
                        $jsonInput = json_decode($toolCall['function']['arguments'], true)['json_input'];
                        $fixedJson = $this->validateJson($jsonInput);

                        // Log the fixed JSON for debugging
                        Log::info('Fixed JSON:', [$fixedJson]);

                        return $fixedJson;
                    }
                }
            }

            // Fallback to the raw content if no tools were used
            $output = $responseData['choices'][0]['message']['content'];
            return $output;

        } catch (\Exception $e) {
            Log::error('Exception occurred:', [$e->getMessage()]);
            return ['error' => 'An unexpected error occurred.'];
        }
    }

// /**
//  * A helper function to manually validate and fix JSON if needed.
//  */
    private function validateJson($jsonInput)
    {
        $decoded = json_decode($jsonInput);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT);
        }

        // Attempt to fix JSON (e.g., removing trailing commas)
        $fixed = preg_replace('/,\s*([\]}])/', '$1', $jsonInput);

        // Retry decoding
        $decoded = json_decode($fixed);

        if (json_last_error() === JSON_ERROR_NONE) {
            return json_encode($decoded, JSON_PRETTY_PRINT);
        }

        // Log error if unable to fix
        Log::error('Failed to fix JSON:', [json_last_error_msg()]);
        return ['error' => 'Invalid JSON could not be fixed.'];
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

    // public function generateAudioAndTranscript(Request $request)
    // {

    //     $base64Credentials = env('GOOGLE_APPLICATION_CREDENTIALS_BASE64');

    //     if (!$base64Credentials) {
    //         return response()->json(['error' => 'Base64 credentials missing in .env'], 500);
    //     }

    //     // Decode Base64 and log
    //     $decodedJson = base64_decode($base64Credentials);
    //     if (!$decodedJson) {
    //         return response()->json(['error' => 'Failed to decode Base64 credentials'], 500);
    //     }

    //     $credentials = json_decode($decodedJson, true);
    //     if (!$credentials) {
    //         return response()->json(['error' => 'Decoded JSON is invalid'], 500);
    //     }

    //     // Log credentials to ensure it's working
    //     Log::info('Decoded Credentials:', $credentials);

    //     // $textToSpeechClient = null; // Declare the variable outside of the try block

    //     $text = $request->input('text');
    //     $id = $request->input('id');

    //     try {
    //         // Initialize the Text-to-Speech client
    //         $textToSpeechClient = new TextToSpeechClient([
    //             'credentials' => $credentials, // Directly pass credentials as an array
    //         ]);
    //         // Set the text input to be synthesized
    //         $input = new SynthesisInput();
    //         $input->setText($text);

    //         // Select the voice parameters
    //         $voice = new VoiceSelectionParams();
    //         $voice->setLanguageCode('en-US'); // Change this to your desired language code
    //         $voice->setSsmlGender(SsmlVoiceGender::FEMALE); // Change this to your desired gender

    //         // Set the audio configuration
    //         $audioConfig = new AudioConfig();
    //         $audioConfig->setAudioEncoding(AudioEncoding::MP3); // Use MP3 format

    //         // Call the Text-to-Speech API
    //         $synthesizeSpeechRequest = new \Google\Cloud\TextToSpeech\V1\SynthesizeSpeechRequest();
    //         $synthesizeSpeechRequest->setInput($input);
    //         $synthesizeSpeechRequest->setVoice($voice);
    //         $synthesizeSpeechRequest->setAudioConfig($audioConfig);

    //         $resp = $textToSpeechClient->synthesizeSpeech($synthesizeSpeechRequest);

    //         // Save the synthesized audio to a temporary file
    //         $audioFilePath = storage_path('app/public/test.mp3');
    //         file_put_contents($audioFilePath, $resp->getAudioContent());

    //         // Upload the audio file to Firebase Storage
    //         $firebaseStorage = Firebase::storage();
    //         $bucket = $firebaseStorage->getBucket();

    //         $firebaseFilePath = 'audios/test_' . $id . '.mp3'; // Path in Firebase Storage
    //         $file = fopen($audioFilePath, 'r');
    //         $bucket->upload($file, ['name' => $firebaseFilePath]);

    //         // Get the public URL of the uploaded file
    //         $fileReference = $bucket->object($firebaseFilePath);
    //         $fileUrl = $fileReference->signedUrl(new \DateTime('+7 days')); // Signed URL valid for a day

    //         // Optionally store the file URL in Firebase Database
    //         $database = Firebase::database();
    //         $database->getReference('audio_files/' . $id)->set([
    //             'url' => $fileUrl,
    //             'created_at' => now(),
    //         ]);

    //         // Call AssemblyAI for transcription
    //         $assemblyApiKey = env('ASSEMBLYAI_API_KEY'); // Make sure to set this in your .env
    //         $uploadUrl = $this->uploadFile($assemblyApiKey, $audioFilePath);
    //         $transcript = $this->createTranscript($assemblyApiKey, $uploadUrl);

    //         // Optionally return the transcript along with the audio URL
    //         return response()->json([
    //             'message' => 'Audio generated, uploaded, and transcribed successfully.',
    //             'url' => $fileUrl,
    //             'transcript' => $transcript['words'] ?? 'No transcript available.',
    //         ], 200);

    //     } catch (Exception $e) {
    //         // Handle any exceptions that occur
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     } finally {
    //         // Close the client if it was initialized
    //         if ($textToSpeechClient) {
    //             $textToSpeechClient->close();
    //         }
    //     }
    // }
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
