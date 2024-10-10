import { Link } from "@inertiajs/react";
import PrimaryButton from "./PrimaryButton";

type Props = {};

const EmptyState = (props: Props) => {
  return (
    <div className="flex flex-col gap-3 items-center justify-center p-5 mt-10 border-2 border-dotted py-24">
      <h2>You do not have any short video created</h2>
      <Link href="/create-new">
        <PrimaryButton className="bg-primary">
          Create New Short Video
        </PrimaryButton>
      </Link>
    </div>
  );
};

export default EmptyState;
