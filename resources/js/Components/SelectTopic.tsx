import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";
import { useState } from "react";
import { Textarea } from "@/Components/ui/textarea";

type Props = {
  onUserSelect: (type: string, name: string) => void;
};

const SelectTopic = ({ onUserSelect }: Props) => {
  const options = [
    "Custom Prompt",
    "Random AI story",
    "Scary Story",
    "Historical Facts",
    "Bed Time Story",
    "Motivational",
    "Fun Facts",
  ];

  const [selectedOptin, setSelectedOption] = useState("");

  return (
    <div>
      <h2 className="font-bold text-2xl text-primary">Content</h2>

      <p className="text-gray-500">What is the topic of your video?</p>

      <Select
        onValueChange={(value) => {
          setSelectedOption(value);
          value != "Custom Prompt" && onUserSelect("topic", value);
        }}
      >
        <SelectTrigger className="w-full mt-2 p-6 text-lg">
          <SelectValue placeholder="Content Type" />
        </SelectTrigger>
        <SelectContent>
          {options.map((option, index) => (
            <SelectItem value={option} key={index}>
              {option}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>

      {selectedOptin === "Custom Prompt" && (
        <Textarea
          onChange={(e) => onUserSelect("topic", e.target.value)}
          className="mt-3 focus:outline-none"
          placeholder="Enter your prompt"
        />
      )}
    </div>
  );
};

export default SelectTopic;
