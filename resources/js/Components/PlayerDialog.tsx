import { useEffect, useState } from "react";
import axios from "axios";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { Player } from "@remotion/player";
import RemotionVideo from "./RemotionVideo";
import PrimaryButton from "./PrimaryButton";
import DangerButton from "./DangerButton";

type Video = {
  id: number;
  videoAudio: string;
  videoImages: string[];
  videoScript: { contextText: string; imagePrompt: string }[];
  videoTranscript: {
    start: number;
    end: number;
    text: string;
    speaker: string | null;
    confidence: number;
  }[];
  user_id: number;
  created_at: string;
  updated_at: string;
};

type Props = {
  playVideo: boolean;
  videoId: number | undefined;
};

const PlayerDialog = ({ playVideo, videoId }: Props) => {
  const [openDialog, setOpenDialog] = useState(false);
  const [videoData, setVideoData] = useState<Video | null>(null);
  const [durationInFrame, setDurationInFrame] = useState(100);

  useEffect(() => {
    setOpenDialog(playVideo);
    if (videoId) getVideo();
  }, [playVideo, videoId]);

  useEffect(() => {
    if (videoData) {
      console.log("Updated video data:", videoData);
    }
  }, [videoData]);

  const getVideo = async () => {
    try {
      const res = await axios.get<{ video: Video }>("/get-video", {
        params: { id: videoId },
      });

      if (res.data.video) {
        setVideoData(res.data.video);
      } else {
        console.error("No video data found in the response.");
      }
    } catch (error) {
      console.error("Error fetching video:", error);
    }
  };

  return (
    <Dialog open={openDialog}>
      <DialogContent className="flex items-center justify-center flex-col">
        <DialogHeader>
          <DialogTitle className="text-3xl font-bold my-5 text-center">
            Your video is ready
          </DialogTitle>
          <DialogDescription>
            {videoData && (
              <Player
                component={RemotionVideo}
                durationInFrames={Number(durationInFrame.toFixed(0))}
                compositionWidth={300}
                compositionHeight={450}
                fps={30}
                controls={true}
                inputProps={{
                  ...videoData,
                  setDurationValue: (frameValue) =>
                    setDurationInFrame(frameValue),
                }}
              />
            )}

            <div className="flex justify-center items-center gap-3 mt-4">
              <DangerButton>Cancel</DangerButton>
              <PrimaryButton className="bg-primary">Export</PrimaryButton>
            </div>
          </DialogDescription>
        </DialogHeader>
      </DialogContent>
    </Dialog>
  );
};
export default PlayerDialog;
