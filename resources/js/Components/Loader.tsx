import { AlertDialog, AlertDialogContent } from "@/Components/ui/alert-dialog";

type Props = {
  loading: boolean;
};

const Loader = ({ loading }: Props) => {
  return (
    <AlertDialog open={loading}>
      <AlertDialogContent>
        <div className="flex flex-col justify-center items-center">
          <img
            src="/progress.gif"
            alt="loading..."
            width={100}
            height={100}
            className="text-center"
          />
          <h2>Generating your video do not refresh...</h2>
        </div>
      </AlertDialogContent>
    </AlertDialog>
  );
};

export default Loader;
