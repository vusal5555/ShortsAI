import { createContext } from "react";

export const VideoContext = createContext<any>({
  videoData: {},
  setVideoData: () => {},
});
