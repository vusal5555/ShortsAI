import Duration from "@/Components/Duration";
import Loader from "@/Components/Loader";
import PlayerDialog from "@/Components/PlayerDialog";
import PrimaryButton from "@/Components/PrimaryButton";
import SelectStyle from "@/Components/SelectStyle";
import SelectTopic from "@/Components/SelectTopic";
import { VideoContext } from "@/context/context";
import MainLayout from "@/Layouts/MainLayout";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { useContext, useEffect, useState } from "react";
// @ts-ignore
import { v4 as uuidv4 } from "uuid";

type Props = {};

const index = (props: Props) => {
  const [loading, setLoading] = useState(false);
  const [videoScript, setVideoScript] = useState([]);
  const [audioFileUrl, setAudioFileUrl] = useState("");
  const [transcipt, setTranscript] = useState([]);
  const [images, setImages] = useState<any[]>([]);
  const { videoData, setVideoData } = useContext(VideoContext);
  const [playVideo, setPlayVideo] = useState(false);
  const [videoId, setVideoId] = useState();
  const [formData, setFormData] = useState({
    topic: "",
    style: "",
    duration: "",
  });
  const onHandleInputChange = (fieldName: string, fieldValue: string) => {
    setFormData((prev) => ({ ...prev, [fieldName]: fieldValue }));
  };

  const getVideoScript = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);
    const response = await axios.post("/generate-video-script", {
      input: `write a script to generate ${formData.duration} seconds video on ${formData.topic}: interesting historical story along with ai image prompt in ${formData.style} format for each scene and give me result in JSON format with imagePrompt and Context Text as field`,
    });

    setVideoScript(response.data);

    generateAudioFile(response);
    generateImage(response);

    if (response.data) {
      setVideoData((prev: any) => ({
        ...prev,
        videoScript: response.data,
      }));
    }
  };

  const generateAudioFile = async (response: any) => {
    setLoading(true);
    let script = "";
    const id = uuidv4();

    response.data.forEach((item: any) => {
      script += item.contextText + " ";
    });

    const res = await axios.post("/generate-audio-transcript", {
      text: script,
      id,
    });

    // Check if `res.data` is a string and parse it
    let data = typeof res.data === "string" ? JSON.parse(res.data) : res.data;

    // Destructure the parsed response data
    const { url, transcript } = data; // Corrected 'transcipt' to 'transcript'

    // Update the state with the parsed response data
    setAudioFileUrl(url);

    // Update the state with the parsed response data
    setTranscript(transcript); // Ensure 'transcript' is used here
    setVideoData((prev: any) => ({
      ...prev,
      videoAudio: url,
      videoTranscript: transcript,
    }));
  };

  const generateImage = async (response: any) => {
    const id = uuidv4();
    setLoading(true);

    let images = [];

    // Use a Set to keep track of prompts already processed
    const processedPrompts = new Set();

    for (const item of response.data) {
      const prompt = item.imagePrompt;

      // Only proceed if the prompt has not been processed yet
      if (!processedPrompts.has(prompt)) {
        processedPrompts.add(prompt); // Mark this prompt as processed

        try {
          const res = await axios.post("/generate-images", {
            prompt,
            id,
          });
          console.log(res.data.result);
          images.push(res.data.result);
        } catch (error) {
          console.error("Error generating image:", error);
        }
      }
    }

    setVideoData((prev: any) => ({
      ...prev,
      videoImages: images,
    }));

    // setImages(images); // Update the state with the generated images
  };

  useEffect(() => {
    if (Object.keys(videoData).length == 4) {
      saveVideo(videoData);
    }
  }, [videoData]);

  const saveVideo = async (videoData: []) => {
    setLoading(true);

    const res = await axios.post("/generate-video", videoData);

    console.log(res.data);

    setVideoId(res.data.video.id);
    setPlayVideo(true);

    setLoading(false);
  };

  return (
    <>
      <Head title="Create Video"></Head>
      <MainLayout>
        <div className="md:px-20">
          <h2 className="font-extrabold text-4xl text-primary text-center">
            Create New Video
          </h2>

          <form onSubmit={getVideoScript} className="mt-10 shadow-md p-10">
            <SelectTopic onUserSelect={onHandleInputChange}></SelectTopic>

            <SelectStyle onUserSelect={onHandleInputChange}></SelectStyle>

            <Duration onUserSelect={onHandleInputChange}></Duration>

            <PrimaryButton
              type="submit"
              className="bg-primary mt-5 w-full flex items-center justify-center py-3"
            >
              Create short video
            </PrimaryButton>
          </form>
        </div>
        <Loader loading={loading}></Loader>

        <PlayerDialog playVideo={playVideo} videoId={videoId}></PlayerDialog>
      </MainLayout>
    </>
  );
};

export default index;
