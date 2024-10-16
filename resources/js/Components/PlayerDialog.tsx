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
import Loader from "./Loader";

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
  playVideo: boolean | number;
  videoId: number | undefined;
  onClose: () => void; // Add a callback to handle closing the dialog.
};

const PlayerDialog = ({ playVideo, videoId, onClose }: Props) => {
  const [videoData, setVideoData] = useState<Video | null>(null);
  const [durationInFrame, setDurationInFrame] = useState(100);
  const [loading, setLoading] = useState(false); // Loading state

  useEffect(() => {
    if (playVideo && videoId) {
      setLoading(true); // Start loading
      setVideoData(null); // Reset old video data to prevent flashing
      getVideo();
    }
  }, [playVideo, videoId]);

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
    } finally {
      setLoading(false); // Stop loading after fetching
    }
  };

  return (
    <Dialog open={Boolean(playVideo)} onOpenChange={onClose}>
      <DialogContent className="flex items-center justify-center flex-col">
        <DialogHeader>
          <DialogTitle className="text-3xl font-bold my-5 text-center">
            Your video is ready
          </DialogTitle>

          <DialogDescription>
            {loading && <Loader loading={loading} isDisplayed={true}></Loader>}{" "}
            {/* Show loading message */}
            {!loading && videoData && (
              <>
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

                <div className="flex justify-center items-center gap-3 mt-4">
                  <DangerButton
                    onClick={() => {
                      onClose(); // Use the onClose callback to close the dialog.
                    }}
                  >
                    Cancel
                  </DangerButton>

                  <PrimaryButton className="bg-primary">Export</PrimaryButton>
                </div>
              </>
            )}
          </DialogDescription>
        </DialogHeader>
      </DialogContent>
    </Dialog>
  );
};
export default PlayerDialog;
