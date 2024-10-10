import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";

type Props = {
  onUserSelect: (type: string, name: string) => void;
};

const Duration = ({ onUserSelect }: Props) => {
  return (
    <div className="mt-7">
      <h2 className="font-bold text-2xl text-primary">Content</h2>

      <p className="text-gray-500">What is the duration of your video?</p>

      <Select
        onValueChange={(value) => {
          onUserSelect("duration", value);
        }}
      >
        <SelectTrigger className="w-full mt-2 p-6 text-lg">
          <SelectValue placeholder="Duration" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="30 seconds">30</SelectItem>
          <SelectItem value="60 seconds">60</SelectItem>
        </SelectContent>
      </Select>
    </div>
  );
};

export default Duration;
