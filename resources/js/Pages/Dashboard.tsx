import EmptyState from "@/Components/EmptyState";
import { Loader } from "lucide-react";
import PrimaryButton from "@/Components/PrimaryButton";
import VideoList from "@/Components/VideoList";
import MainLayout from "@/Layouts/MainLayout";
import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import { useEffect, useState } from "react";

export default function Dashboard() {
  const [videoList, setVideoList] = useState<any[]>([]);
  const [loading, setLoading] = useState(true); // Add loading state
  const user = usePage().props.auth.user;

  useEffect(() => {
    if (user) {
      getVideos();
    }
  }, [user]);

  const getVideos = async () => {
    try {
      const res = await axios.get("/get-all-videos");
      setVideoList(res.data.videos);
    } catch (error) {
      console.error("Error fetching videos:", error);
    } finally {
      setLoading(false); // Stop loading after the data is fetched
    }
  };

  return (
    <>
      <Head title="Dashboard" />
      <MainLayout>
        <h2 className="text-4xl mt-20 uppercase text-center text-primary font-extrabold">
          Dashboard
        </h2>

        <div>
          {loading ? ( // Show loader or placeholder while loading
            <div className="flex justify-center items-center h-screen">
              <Loader
                className="animate-spin text-primary"
                width={50}
                height={50}
              ></Loader>
            </div>
          ) : // You can replace this with a Loader component if needed
          videoList.length === 0 ? (
            <EmptyState /> // Render EmptyState only if no videos are found
          ) : (
            <VideoList videoList={videoList} /> // Render VideoList when data is available
          )}
        </div>
      </MainLayout>
    </>
  );
}
