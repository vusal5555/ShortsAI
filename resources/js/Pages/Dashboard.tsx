import EmptyState from "@/Components/EmptyState";
import { Loader } from "lucide-react";
import VideoList from "@/Components/VideoList";
import MainLayout from "@/Layouts/MainLayout";
import { Head } from "@inertiajs/react";
import { useState } from "react";
import PaginatonComponent from "@/Components/Pagination";

type VideoData = {
  data: any;
};

export default function Dashboard({ videos }: any) {
  const [loading, setLoading] = useState(false); // Add loading state

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
          videos.length === 0 ? (
            <EmptyState /> // Render EmptyState only if no videos are found
          ) : (
            <VideoList videoList={videos.data} /> // Render VideoList when data is available
          )}
        </div>
        <PaginatonComponent links={videos.links} />
      </MainLayout>
    </>
  );
}
