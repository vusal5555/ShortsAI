import "../css/app.css";
import "./bootstrap";

import { createInertiaApp } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";
import { VideoContext } from "./context/context";
import React, { useState } from "react";

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

// Create a functional component to wrap your App with VideoContext
const AppWrapper = ({ children }: { children: React.ReactNode }) => {
  const [videoData, setVideoData] = useState<any[]>([]); // Use useState correctly here

  return (
    <VideoContext.Provider value={{ videoData, setVideoData }}>
      {children}
    </VideoContext.Provider>
  );
};

createInertiaApp({
  title: (title) => `${title} - ${appName}`,
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob("./Pages/**/*.tsx")
    ),
  setup({ el, App, props }) {
    const root = createRoot(el);

    // Render the App inside the AppWrapper component
    root.render(
      <AppWrapper>
        <App {...props} />
      </AppWrapper>
    );
  },
  progress: {
    color: "#4B5563",
  },
});
