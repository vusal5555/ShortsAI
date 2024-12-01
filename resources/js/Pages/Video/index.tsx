import Duration from "@/Components/Duration";
import Loader from "@/Components/Loader";
import PlayerDialog from "@/Components/PlayerDialog";
import PrimaryButton from "@/Components/PrimaryButton";
import SelectStyle from "@/Components/SelectStyle";
import SelectTopic from "@/Components/SelectTopic";
import { VideoContext } from "@/context/context";
import MainLayout from "@/Layouts/MainLayout";
import { Head, usePage } from "@inertiajs/react";
import axios from "axios";
import { useContext, useEffect, useState } from "react";
import { toast } from "sonner";
// @ts-ignore
import { v4 as uuidv4 } from "uuid";

const Index = () => {
  const [loading, setLoading] = useState(false);
  const [audioFileUrl, setAudioFileUrl] = useState("");
  const [transcript, setTranscript] = useState([]);
  const { videoData, setVideoData } = useContext(VideoContext);
  const [playVideo, setPlayVideo] = useState(false);
  const [videoId, setVideoId] = useState<number | undefined>();
  const [formData, setFormData] = useState({
    topic: "",
    style: "",
    duration: "",
  });
  const [videoGenerated, setVideoGenerated] = useState(false); // New state to track video generation

  const user = usePage().props.auth.user;
  const resetState = () => {
    setVideoData({}); // Clear video data on reset
    setAudioFileUrl("");
    setTranscript([]);
    setVideoId(undefined);
  };

  const onHandleInputChange = (fieldName: string, fieldValue: string) => {
    setFormData((prev) => ({ ...prev, [fieldName]: fieldValue }));
  };

  // const getVideoScript = async (e: React.FormEvent<HTMLFormElement>) => {
  //   e.preventDefault();

  //   if (videoGenerated) return;

  //   setLoading(true);

  //   try {
  //     if (!formData.duration || !formData.topic || !formData.style) {
  //       throw new Error("Invalid form data");
  //     }

  //     const input = `Write a script for a ${formData.duration}-second video on "${formData.topic}", including an AI image prompt in the "${formData.style}" style for each scene.`;

  //     console.log("Sending input to server:", input);

  //     const response = await axios.post("/generate-video-script", { input });

  //     console.log("Full response:", response);

  //     // Change this line to directly access the data array
  //     const scriptData = response.data.data;

  //     if (scriptData && Array.isArray(scriptData)) {
  //       setVideoData((prev: any) => ({
  //         ...prev,
  //         videoScript: scriptData,
  //       }));
  //       console.log("Script Data:", scriptData);
  //       await generateAudioFile(scriptData);
  //       await generateImage(scriptData);
  //     } else {
  //       console.error(
  //         "Expected scriptData to be an array, but got:",
  //         scriptData
  //       );
  //     }
  //   } catch (error) {
  //     console.error("Error generating video script:", error);
  //   } finally {
  //     setLoading(false);
  //   }
  // };
  // const getVideoScript = async (e: React.FormEvent<HTMLFormElement>) => {
  //   e.preventDefault();

  //   if (videoGenerated) return;

  //   setLoading(true);

  //   try {
  //     if (!formData.duration || !formData.topic || !formData.style) {
  //       throw new Error("Invalid form data");
  //     }

  //     const input = `Write a script for a ${formData.duration}-second video on "${formData.topic}", including an AI image prompt in the "${formData.style}" style for each scene.`;

  //     const response = await axios.post("/generate-video-script", { input });

  //     const parsedData = JSON.parse(response.data);

  //     console.log("Full response:", parsedData);

  //     await generateAudioFile(parsedData);
  //     await generateImage(parsedData);
  //   } catch (error) {
  //     console.error("Error generating video script:", error);
  //   } finally {
  //     setLoading(false);
  //   }
  // };

  const getVideoScript = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();

    if (videoGenerated) return;

    setLoading(true);

    try {
      if (!formData.duration || !formData.topic || !formData.style) {
        throw new Error("Invalid form data");
      }

      const input = `Write a script for a ${formData.duration}-second video on "${formData.topic}", including an AI image prompt in the "${formData.style}" style for each scene.`;

      const response = await axios.post("/generate-video-script", { input });

      console.log("Raw response:", response.data);
      console.log("Type of response:", typeof response.data);

      let parsedData: any;

      parsedData = JSON.parse(response.data);

      // Fallback: Attempt to fix the JSON manually
      const fixedJson = response.data.trim().replace(/,\s*$/, "");
      console.log("Fixed JSON:", fixedJson);

      parsedData = JSON.parse(fixedJson);

      setVideoData((prev: any) => ({
        ...prev,
        videoScript: parsedData,
      }));

      console.log(videoData);
      await generateAudioFile(parsedData);
      await generateImage(parsedData);

      if (!Array.isArray(parsedData)) {
        throw new Error("API response is not an array of objects");
      }
    } catch (error) {
      console.error("Error generating video script:", error);
    } finally {
      setLoading(false);
    }
  };

  const generateAudioFile = async (scriptData: any) => {
    console.log("Script Data:", scriptData);
    setLoading(true);
    const id = uuidv4();
    const script = scriptData.map((item: any) => item.contextText).join(" ");

    try {
      const res = await axios.post("/generate-audio-transcript", {
        text: script,
        id,
      });

      const data =
        typeof res.data === "string" ? JSON.parse(res.data) : res.data;
      setAudioFileUrl(data.url);
      setTranscript(data.transcript);

      setVideoData((prev: any) => ({
        ...prev,
        videoAudio: data.url,
        videoTranscript: data.transcript,
      }));
    } catch (error) {
      console.error("Error generating audio:", error);
    } finally {
      setLoading(false);
    }
  };

  const generateImage = async (scriptData: any) => {
    setLoading(true);
    const id = uuidv4();
    const images: any[] = [];
    const processedPrompts = new Set();

    try {
      for (const item of scriptData) {
        const { imagePrompt } = item;

        if (!processedPrompts.has(imagePrompt)) {
          processedPrompts.add(imagePrompt);
          const res = await axios.post("/generate-images", {
            prompt: imagePrompt,
            id,
          });
          images.push(res.data.result);
        }
      }

      setVideoData((prev: any) => ({
        ...prev,
        videoImages: images,
      }));
    } catch (error) {
      console.error("Error generating images:", error);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    // Reset only if the video hasn't been generated yet
    if (!videoGenerated) {
      resetState();
    }
  }, [videoGenerated]);

  const saveVideo = async (videoData: any) => {
    setLoading(true);
    try {
      const res = await axios.post("/generate-video", videoData);
      setVideoId(res.data.video.id);
      await updateUserCredits();
      setPlayVideo(true);
      setVideoGenerated(true);
    } catch (error) {
      console.error("Error saving video:", error);
    } finally {
      setLoading(false);
    }
  };

  const handleCloseDialog = () => {
    setPlayVideo(false);
    setVideoId(undefined);
  };

  const onCreateClickHandler = (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    if (user?.credits > 0) {
      getVideoScript(e);
    } else {
      toast("You do not have enough credits");
    }
  };

  const updateUserCredits = async () => {
    try {
      await axios.patch("/updateUserCredits");
      setVideoData(null);
    } catch (error) {
      console.error("Error updating user credits:", error);
    }
  };

  useEffect(() => {
    if (videoData && Object.keys(videoData).length === 4) {
      saveVideo(videoData);
    }
  }, [videoData]);

  console.log(formData);
  return (
    <>
      <Head title="Create Video" />
      <MainLayout>
        <div>
          <h2 className="font-extrabold mt-20 uppercase text-4xl text-primary text-center">
            Create New Video
          </h2>

          <form
            onSubmit={onCreateClickHandler}
            className="mt-10 shadow-md p-10"
          >
            <SelectTopic onUserSelect={onHandleInputChange} />
            <SelectStyle onUserSelect={onHandleInputChange} />
            <Duration onUserSelect={onHandleInputChange} />

            <PrimaryButton
              type="submit"
              className="bg-primary mt-5 w-full flex items-center justify-center py-3"
            >
              Create short video
            </PrimaryButton>
          </form>
        </div>

        <Loader loading={loading} />

        <PlayerDialog
          onClose={handleCloseDialog}
          playVideo={playVideo}
          videoId={videoId}
        />
      </MainLayout>
    </>
  );
};

export default Index;
