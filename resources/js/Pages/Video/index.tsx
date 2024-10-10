import Duration from "@/Components/Duration";
import Loader from "@/Components/Loader";
import PrimaryButton from "@/Components/PrimaryButton";
import SelectStyle from "@/Components/SelectStyle";
import SelectTopic from "@/Components/SelectTopic";
import MainLayout from "@/Layouts/MainLayout";
import { Head } from "@inertiajs/react";
import axios from "axios";
import { useState } from "react";
// @ts-ignore
import { v4 as uuidv4 } from "uuid";

type Props = {};

const index = (props: Props) => {
  const [loading, setLoading] = useState(false);
  const [videoScript, setVideoScript] = useState([]);
  const [formData, setFormData] = useState({
    topic: "",
    style: "",
    duration: "",
  });
  const onHandleInputChange = (fieldName: string, fieldValue: string) => {
    console.log(fieldName, fieldValue);
    setFormData((prev) => ({ ...prev, [fieldName]: fieldValue }));
  };

  const getVideoScript = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setLoading(true);
    const response = await axios.post("/generate-video-script", {
      input: `write a script to generate ${formData.duration} seconds video on ${formData.topic}: interesting historical story along with ai image prompt in ${formData.style} format for each scene and give me result in JSON format with imagePrompt and Context Text as field`,
    });

    setVideoScript(response.data);
    console.log(response.data);
    generateAudioFile(response);
    setLoading(false);
  };

  const generateAudioFile = async (response: any) => {
    let script = "";
    const id = uuidv4();
    console.log(id);
    response.data.forEach((item: any) => {
      script += item.contextText + " ";
    });
    console.log(script);
    const res = await axios.post("/generate-audio", {
      text: script,
      id,
    });
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
      </MainLayout>
    </>
  );
};

export default index;
