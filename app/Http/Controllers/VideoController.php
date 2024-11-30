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
use Kreait\Laravel\Firebase\Facades\Firebase;
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
        $apiKey = env('GEMINI_API_KEY');
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent?key={$apiKey}";

        Log::info('API URL:', [$input]);

        $prompt = "{$input}

        Provide the output in strict JSON format, ensuring the following structure:

        [
            {
                \"imagePrompt\": \"A vivid and imaginative description of a unique scene. Be as creative and detailed as possible.\",
                \"contextText\": \"An engaging and innovative text explaining the scene. Add unexpected and intriguing elements to captivate the reader.\"
            },
            ...
        ]

        Avoid redundancy or repetitive ideas. Each description should be fresh, unique, and inspire curiosity. Make sure the JSON is valid, properly escaped, and does not include trailing commas or syntax errors. Validate the output to confirm it is parsable as JSON before returning it.";

        $data = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [

                            'text' => $prompt,

                        ],
                    ],
                ],
            ],
            // 'generationConfig' => [
            //     'temperature' => 0.8, // Increase creativity
            //     'topK' => 40,
            //     'topP' => 0.85,
            //     'maxOutputTokens' => 8192,
            //     'responseMimeType' => 'application/json',
            // ],
            'generationConfig' => [
                'temperature' => 1.5, // Higher temperature for more randomness
                'topK' => 0, // Disable top-K sampling for broader exploration
                'topP' => 0.99, // Encourage diversity with a wider token range
                'maxOutputTokens' => 1024, // Limit tokens to ensure concise outputs
                'responseMimeType' => 'application/json',
            ],

        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($url, $data);

            if ($response->failed()) {
                Log::error('API Request Failed', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
                return ['error' => 'API request failed.'];
            }

            $responseJson = $response->json();

            // Validate response structure
            if (isset($responseJson['candidates'][0]['content']['parts'][0]['text'])) {
                $generatedText = $responseJson['candidates'][0]['content']['parts'][0]['text'];

                // Clean the text to make it valid JSON
                $cleanedText = stripslashes($generatedText);
                $cleanedText = preg_replace('/,\s*}/', '}', $cleanedText); // Fix trailing commas in objects
                $cleanedText = preg_replace('/,\s*\]/', ']', $cleanedText); // Fix trailing commas in arrays

                Log::info('Cleaned Text:', [$cleanedText]);

                // Decode the cleaned JSON structure
                $decodedResult = json_decode($cleanedText, true, 512, JSON_THROW_ON_ERROR);

                return $decodedResult;
            } else {
                Log::error('Expected content not found in the response.', [$responseJson]);
            }

        } catch (\JsonException $e) {
            Log::error('JSON Decode Exception:', [$e->getMessage()]);
            return ['error' => 'Failed to decode JSON.'];
        } catch (\Exception $e) {
            Log::error('Exception occurred:', [$e->getMessage()]);
            return ['error' => 'An unexpected error occurred.'];
        }

        return ['error' => 'Failed to decode content.'];
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

        // Decode Base64 and log
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

        // $textToSpeechClient = null; // Declare the variable outside of the try block

        $text = $request->input('text');
        $id = $request->input('id');

        try {
            // Initialize the Text-to-Speech client
            $textToSpeechClient = new TextToSpeechClient([
                'credentials' => $credentials, // Directly pass credentials as an array
            ]);
            // Set the text input to be synthesized
            $input = new SynthesisInput();
            $input->setText($text);

            // Select the voice parameters
            $voice = new VoiceSelectionParams();
            $voice->setLanguageCode('en-US'); // Change this to your desired language code
            $voice->setSsmlGender(SsmlVoiceGender::FEMALE); // Change this to your desired gender

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

            // Upload the audio file to Firebase Storage
            $firebaseStorage = Firebase::storage();
            $bucket = $firebaseStorage->getBucket();

            $firebaseFilePath = 'audios/test_' . $id . '.mp3'; // Path in Firebase Storage
            $file = fopen($audioFilePath, 'r');
            $bucket->upload($file, ['name' => $firebaseFilePath]);

            // Get the public URL of the uploaded file
            $fileReference = $bucket->object($firebaseFilePath);
            $fileUrl = $fileReference->signedUrl(new \DateTime('+7 days')); // Signed URL valid for a day

            // Optionally store the file URL in Firebase Database
            $database = Firebase::database();
            $database->getReference('audio_files/' . $id)->set([
                'url' => $fileUrl,
                'created_at' => now(),
            ]);

            // Call AssemblyAI for transcription
            $assemblyApiKey = env('ASSEMBLYAI_API_KEY'); // Make sure to set this in your .env
            $uploadUrl = $this->uploadFile($assemblyApiKey, $audioFilePath);
            $transcript = $this->createTranscript($assemblyApiKey, $uploadUrl);

            // Optionally return the transcript along with the audio URL
            return response()->json([
                'message' => 'Audio generated, uploaded, and transcribed successfully.',
                'url' => $fileUrl,
                'transcript' => $transcript['words'] ?? 'No transcript available.',
            ], 200);

        } catch (Exception $e) {
            // Handle any exceptions that occur
            return response()->json(['error' => $e->getMessage()], 500);
        } finally {
            // Close the client if it was initialized
            if ($textToSpeechClient) {
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
                ->withServiceAccount($decodedCredentials);

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
