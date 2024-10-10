import { useState } from "react";

type Props = {
  onUserSelect: (type: string, name: string) => void;
};

const SelectStyle = ({ onUserSelect }: Props) => {
  const options = [
    { name: "Realistic", image: "realistic.webp" },
    { name: "Cartoon", image: "cartoon.webp" },
    { name: "Comic", image: "comic.webp" },
    { name: "WaterColor", image: "watercolor.webp" },
    { name: "GTA", image: "gta.webp" },
  ];

  const [selectedOption, setSelectedOption] = useState("");

  return (
    <div className="mt-7">
      <h2 className="font-bold text-2xl text-primary">Style</h2>

      <p className="text-gray-500">What is the style of your video?</p>

      <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-5 mt-5">
        {options.map((option, index) => (
          <div
            className={`relative hover:scale-105 transition-all cursor-pointer rounded-xl ${
              selectedOption === option.name ? "border-4 border-primary " : ""
            }`}
            key={index}
          >
            <img
              src={`/${option.image}`}
              className="h-48 object-cover w-full rounded-lg"
              alt={option.name}
              onClick={() => {
                setSelectedOption(option.name);
                onUserSelect("style", option.name);
              }}
            />
            <h1 className="absolute p-1 bg-black bottom-0 w-full text-white text-center rounded-b-lg">
              {option.name}
            </h1>
          </div>
        ))}
      </div>
    </div>
  );
};

export default SelectStyle;
