import PrimaryButton from "@/Components/PrimaryButton";
import { Head, Link } from "@inertiajs/react";

export default function Welcome() {
  return (
    <>
      <Head title="Welcome" />

      <div className="min-h-screen overflow-y-auto bg-slate-100">
        <div className="max-w-7xl mx-auto flex  justify-between p-4">
          <Link href="/dashboard" className="flex gap-3">
            <h2 className="font-extrabold text-xl lg:text-3xl text-primary mb-10">
              Shorts AI
            </h2>
          </Link>

          <Link href="/dashboard">
            <PrimaryButton
              className=" 
              inline-flex justify-center items-center  gap-x-3 text-center bg-gradient-to-tl from-blue-600 to-violet-600 hover:from-violet-600 hover:to-blue-600 border border-transparent text-white text-sm font-medium rounded-md focus:outline-none focus:ring-1 focus:ring-gray-600 py-2 px-2 lg:py-3 lg:px-4 dark:focus:ring-offset-gray-800
              "
            >
              Dashboard
            </PrimaryButton>
          </Link>
        </div>
        <div className="max-w-[85rem] mx-auto flex flex-col items-center justify-center mt-16 lg:mt-20 px-8">
          <h1 className="text-5xl lg:text-6xl text-slate-700 mb-5 font-bold text-center">
            Shorts{" "}
            <span className="bg-clip-text bg-gradient-to-tl from-blue-600 to-violet-600 text-transparent">
              AI
            </span>
          </h1>

          <p className="text-md lg:text-lg text-gray-600 dark:text-neutral-400 text-center w-full max-w-[700px] mb-5">
            Create viral, AI-powered short videos in minutes—no editing skills
            required!
          </p>
          <Link href="/dashboard">
            <PrimaryButton className="inline-flex justify-center items-center  gap-x-3 text-center bg-gradient-to-tl from-blue-600 to-violet-600 hover:from-violet-600 hover:to-blue-600 border border-transparent text-white text-sm font-medium rounded-md focus:outline-none focus:ring-1 focus:ring-gray-600 py-3 px-4 dark:focus:ring-offset-gray-800">
              Get started
            </PrimaryButton>
          </Link>
        </div>

        <div>
          <img
            src="/app.png"
            className="w-full max-w-[800px] mx-auto h-full mt-5 p-2 shadow-2xl"
            alt=""
          />
        </div>
      </div>
    </>
  );
}
