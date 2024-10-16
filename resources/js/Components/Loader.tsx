import { AlertDialog, AlertDialogContent } from "@/Components/ui/alert-dialog";
import { Loader as LoaderLucide } from "lucide-react";

type Props = {
  loading: boolean;
  isDisplayed?: boolean;
};

const Loader = ({ loading, isDisplayed }: Props) => {
  return (
    <AlertDialog open={loading}>
      <AlertDialogContent>
        <div className="flex flex-col justify-center items-center">
          {isDisplayed ? (
            <LoaderLucide className="animate-spin text-primary" />
          ) : (
            <img
              src="/progress.gif"
              alt="loading..."
              width={100}
              height={100}
              className="text-center"
            />
          )}

          {isDisplayed ? (
            <h2>Loading your video, please wait...</h2>
          ) : (
            <h2>Generating your video, do not refresh...</h2>
          )}
        </div>
      </AlertDialogContent>
    </AlertDialog>
  );
};

export default Loader;
