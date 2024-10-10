import Sidebar from "@/Components/Sidebar";

import React from "react";

const MainLayout = ({ children }: { children: React.ReactNode }) => {
  return (
    <div>
      <div className="hidden md:block h-screen bg-white fixed w-64 border border-gray-400/30">
        <Sidebar></Sidebar>
      </div>

      <div>
        <div className="md:ml-64 p-5 h-screen">{children}</div>
      </div>
    </div>
  );
};

export default MainLayout;
