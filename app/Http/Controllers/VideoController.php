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
use Inertia\Inertia;

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
                'temperature' => 1,
                'topK' => 64,
                'topP' => 0.95,
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

    public function generateAudio(Request $request)
    {
        // Set the path to your Google Cloud credentials file
        $credentialsPath = __DIR__ . '/../../../credentials/nodal-algebra-438115-c2-97609a31c69d.json';

        if (file_exists($credentialsPath)) {
            echo "Found credentials file at: " . $credentialsPath . "\n";
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

            // Save the synthesized audio to a file
            file_put_contents('test.mp3', $resp->getAudioContent());

            // Optionally, return a success response
            return response()->json(['message' => 'Audio generated successfully.'], 200);

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
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Video $video)
    {
        //
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
