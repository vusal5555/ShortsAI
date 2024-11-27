import { useState } from "react";
import { Thumbnail } from "@remotion/player";
import RemotionVideo from "./RemotionVideo";
import PlayerDialog from "./PlayerDialog";

type Props = {
  videoList: any[];
};

const VideoList = ({ videoList }: Props) => {
  const [openPlayerDialog, setOpenPlayerDialog] = useState<number | boolean>(
    false
  );
  const [videoId, setVideoId] = useState<number | undefined>(undefined);

  const openDialog = (id: number) => {
    setVideoId(id);
    setOpenPlayerDialog(Date.now()); // Track unique openings.
  };

  const closeDialog = () => {
    setOpenPlayerDialog(false); // Close the dialog.
    setVideoId(undefined); // Reset video ID.
  };

  return (
    <div className="mt-32 w-full lg:max-w-[1700px] mx-auto grid gap-6 grid-cols-1 md:grid-cols-2 lg:grid-cols-4">
      {videoList.map((video, index) => (
        <div
          key={index}
          className="w-full p-2" // Added padding to maintain spacing
          onClick={() => openDialog(video.id)}
          style={{ borderRadius: "20px", overflow: "hidden" }} // Prevent overflow issues
        >
          <Thumbnail
            component={RemotionVideo}
            compositionWidth={4}
            compositionHeight={5}
            frameToDisplay={30}
            durationInFrames={120}
            fps={30}
            style={{
              width: "100%",
              aspectRatio: "4/5",
              borderRadius: "20px",
              overflow: "hidden",
              objectFit: "cover", // Ensure video content respects border-radius
              margin: "0 auto",
            }}
            className="hover:scale-105 transition-all duration-300 cursor-pointer"
            inputProps={{
              ...video,
              setDurationValue: (value) => {
                console.log(value);
              },
            }}
          />
        </div>
      ))}

      <PlayerDialog
        playVideo={openPlayerDialog}
        videoId={videoId}
        onClose={closeDialog}
      />
    </div>
  );
};

export default VideoList;
