import Header from "@/Components/Header";
import Sidebar from "@/Components/Sidebar";
import React from "react";
import { Toaster } from "@/Components/ui/sonner";

const MainLayout = ({ children }: { children: React.ReactNode }) => {
  return (
    <div>
      <div className="hidden lg:block h-screen bg-white fixed w-0 lg:w-64 border border-gray-400/30 z-50">
        <Sidebar></Sidebar>
      </div>

      <div>
        <div className="ml-0 lg:ml-64 p-5 h-screen">
          <Header></Header>

          {children}
          <Toaster></Toaster>
        </div>
      </div>
    </div>
  );
};

export default MainLayout;
