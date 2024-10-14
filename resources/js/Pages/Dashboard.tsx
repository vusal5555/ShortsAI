import EmptyState from "@/Components/EmptyState";
import PrimaryButton from "@/Components/PrimaryButton";
import MainLayout from "@/Layouts/MainLayout";

import { Head, Link, usePage } from "@inertiajs/react";
import axios from "axios";
import { useEffect, useState } from "react";

export default function Dashboard() {
  const [videoList, setVideoList] = useState<any[]>([]);
  const user = usePage().props.auth.user;

  useEffect(() => {
    user && getVideos();
  }, [user]);

  const getVideos = async () => {
    const res = await axios.get("/get-all-videos");

    setVideoList(res.data);
  };

  console.log(videoList);

  return (
    <>
      <Head title="Dashboard" />
      <MainLayout>
        <div className="flex justify-between items-center">
          <h2 className="text-2xl text-primary font-extrabold">Dashboard</h2>
          <Link href="/create-new">
            <PrimaryButton className="bg-primary">+ Create New</PrimaryButton>{" "}
          </Link>
        </div>

        <div>
          {videoList.length === 0 && (
            <div>
              <EmptyState></EmptyState>
            </div>
          )}
        </div>
      </MainLayout>
    </>
  );
}
