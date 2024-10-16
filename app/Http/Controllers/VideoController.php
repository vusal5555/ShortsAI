<?php

namespace App\Http\Controllers;

use App\Models\Video;
use FFI\Exception;
use Google\Cloud\TextToSpeech\V1\AudioConfig;
use Google\Cloud\TextToSpeech\V1\AudioEncoding;
use Google\Cloud\TextToSpeech\V1\SsmlVoiceGender;
use Google\Cloud\TextToSpeech\V1\SynthesisInput;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\TextToSpeech\V1\VoiceSelectionParams;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Kreait\Firebase\Database;
use Kreait\Firebase\Factory; // Firebase Factory for storage
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
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}";

        $data = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => 'write a script to generate 30 seconds video on topic: interesting historical story along with ai image prompt in Realistic format for each scene and give me result in JSON format with imagePrompt and Context Text as field',
                        ],
                    ],
                ],
                [
                    'role' => 'model',
                    'parts' => [
                        [
                            'text' => "## 30-Second Historical Video Script with AI Image Prompts\n\n**Topic:** The Story of the Rosetta Stone\n\n**JSON Format:**\n\n```json\n[\n  {\n    \"imagePrompt\": \"A close-up shot of the Rosetta Stone in a museum, with museum lighting and a crowd of people looking at it. Realistic style.\",\n    \"contextText\": \"In 1799, a French soldier stumbled upon a curious stone in Egypt. It was the Rosetta Stone, and it would change the course of history.\"\n  },\n  {\n    \"imagePrompt\": \"A dramatic illustration of ancient Egyptian hieroglyphs being deciphered. Realistic style with a focus on intricate details.\",\n    \"contextText\": \"The stone had three scripts: hieroglyphs, demotic, and ancient Greek. It was a key to unlocking the secrets of ancient Egypt.\"\n  },\n  {\n    \"imagePrompt\": \"A portrait of Jean-François Champollion, looking focused and determined. Realistic style with a hint of 19th-century lighting.\",\n    \"contextText\": \"It took years, but finally, Jean-François Champollion cracked the code, deciphering the hieroglyphs and opening up a new understanding of ancient Egyptian civilization.\"\n  },\n  {\n    \"imagePrompt\": \"A montage of images showing different historical artifacts and monuments in Egypt, showcasing the impact of the Rosetta Stone's decipherment. Realistic style.\",\n    \"contextText\": \"The Rosetta Stone was a monumental discovery, allowing us to read the stories of ancient Egypt and learn about their culture, beliefs, and history.\"\n  },\n  {\n    \"imagePrompt\": \"A final shot of the Rosetta Stone in its museum case, with a person gazing at it in awe. Realistic style.\",\n    \"contextText\": \"Today, the Rosetta Stone stands as a testament to the power of knowledge and the enduring legacy of ancient Egypt.\"\n  }\n]\n```",
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $input,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 1.8,
                'topK' => 90,
                'topP' => 0.98,
                'maxOutputTokens' => 8192,
                'responseMimeType' => 'application/json',
            ],
        ];

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($url, $data);

        $responseJson = $response->json();

        // Extract the generated content (from the 'candidates' array) and parse the JSON string
        if (isset($responseJson['candidates'][0]['content']['parts'][0]['text'])) {
            $generatedText = $responseJson['candidates'][0]['content']['parts'][0]['text'];

            // Decode the JSON structure within the text field
            $decodedResult = json_decode($generatedText, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decodedResult; // Return the decoded JSON array of objects
            }
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
        // Set the path to your Google Cloud credentials file
        $credentialsPath = __DIR__ . '/../../../credentials/nodal-algebra-438115-c2-97609a31c69d.json';

        if (file_exists($credentialsPath)) {
            putenv("GOOGLE_APPLICATION_CREDENTIALS=" . $credentialsPath);
        } else {
            die("Credentials file not found at: " . $credentialsPath . "\n");
        }

        $textToSpeechClient = null; // Declare the variable outside of the try block

        $text = $request->input('text');
        $id = $request->input('id');

        try {
            // Initialize the Text-to-Speech client
            $textToSpeechClient = new TextToSpeechClient();

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
            $resp = $textToSpeechClient->synthesizeSpeech($input, $voice, $audioConfig);

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
        $replicateApiToken = env('REPLICATE_API_TOKEN'); // Ensure your token is set in .env
        $client = new Replicate($replicateApiToken);

        // Validate and retrieve the prompt from the request
        $prompt = $request->input('prompt');

        try {
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

            // Assume the output contains an image URL
            $imageUrl = $output[0]; // Adjust if needed

            // Download the image and convert to binary
            $imageContent = file_get_contents($imageUrl);

            // Initialize Firebase Storage
            $firebase = (new Factory())
                ->withServiceAccount(base_path('credentials/shortsai-b68d2-07e3adafa0c4.json'));

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
            // Log the error
            Log::error('Replicate API Error: ' . $e->getMessage());

            // Return a JSON error response with status code 500
            return response()->json([
                'error' => 'An error occurred while generating the image.',
                'message' => $e->getMessage(),
            ], 500);
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
            \Log::error('Error saving video data', ['error' => $e->getMessage()]);
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

    public function getAllVideos()
    {
        $videos = Video::where('user_id', auth()->user()->id)->get();
        return response()->json(['videos' => $videos], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Video $video)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Video $video)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Video $video)
    {
        //
    }
}
