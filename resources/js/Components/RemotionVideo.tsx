import React, { useEffect } from "react";
import {
  AbsoluteFill,
  Audio,
  Img,
  interpolate,
  Sequence,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";

export type Video = {
  videoAudio: string;
  videoImages: string[];
  videoScript: VideoScript[];
  videoTranscript: VideoTranscript[];
  setDurationValue: (value: any) => void;
};

type VideoScript = {
  contextText: string;
  imagePrompt: string;
};

type VideoTranscript = {
  start: number;
  end: number;
  text: string;
  speaker: string | null;
  confidence: number;
};

const RemotionVideo = ({
  videoAudio,
  videoImages,
  videoScript,
  videoTranscript,
  setDurationValue,
}: Video) => {
  const { fps } = useVideoConfig();
  const frame = useCurrentFrame();

  // Ensure the function returns the calculated frames.
  const getDurationFrames = () => {
    setDurationValue(
      (videoTranscript[videoTranscript?.length - 1].end / 1000) * fps + 10
    );
    return (
      (videoTranscript[videoTranscript?.length - 1]?.end / 1000) * fps + 10
    );
  };

  const getCurrentCaptions = () => {
    const currentTime = (frame / fps) * 1000;
    const currentCaption = videoTranscript.find(
      (word) => currentTime >= word.start && currentTime <= word.end
    );

    return currentCaption ? currentCaption.text : "";
  };

  useEffect(() => {
    videoImages.forEach((image) => {
      const img = new Image();
      img.src = image;
    });
  }, [videoImages]);

  const totalDuration = getDurationFrames(); // Total duration in frames

  return (
    <AbsoluteFill
      style={{
        backgroundColor: "black",
      }}
    >
      {videoImages.map((image, index) => {
        const startTime = (index * totalDuration) / videoImages?.length;
        const duration = totalDuration;

        const scale = (index: number) =>
          interpolate(
            frame,
            [startTime, startTime + duration / 2, startTime + duration],
            index % 2 === 0 ? [1, 1.8, 1] : [1.8, 1, 1.8],
            {
              extrapolateLeft: "clamp",
              extrapolateRight: "clamp",
            }
          );

        return (
          <Sequence
            key={index}
            from={Math.round(startTime)}
            durationInFrames={
              Math.ceil(getDurationFrames() / videoImages?.length) + 2
            }
          >
            <AbsoluteFill
              style={{ justifyContent: "center", alignItems: "center" }}
            >
              <Img
                src={image}
                className="text-2xl"
                alt={`Frame ${index}`}
                style={{
                  width: "100%",
                  objectFit: "cover",
                  height: "100%",
                  transform: `scale(${scale(index)})`,
                }}
              />

              <AbsoluteFill
                style={{
                  color: "white",
                  justifyContent: "center",
                  top: undefined,
                  bottom: -100,
                  textAlign: "center",
                  width: "100%",
                }}
              >
                <h2 className="text-2xl font-bold uppercase">
                  {getCurrentCaptions()}
                </h2>
              </AbsoluteFill>
            </AbsoluteFill>
          </Sequence>
        );
      })}
      <Audio src={videoAudio}></Audio>
    </AbsoluteFill>
  );
};
export default RemotionVideo;
