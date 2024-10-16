import { Link, usePage } from "@inertiajs/react";
import PrimaryButton from "./PrimaryButton";
import MobileSidebar from "./MobileSidebar";

type Props = {};

const Header = (props: Props) => {
  const { auth } = usePage().props;
  const user = auth.user as { credits?: number };

  const credits =
    user.credits !== undefined ? (user.credits <= 0 ? 0 : user.credits) : 0;

  return (
    <div className="flex justify-between w-full lg:justify-end items-center mb-10 gap-3 fixed top-0  right-0 bg-white z-10 px-5 py-3 border border-gray-300 shadow-md">
      <div className="flex flex-row-reverse items-center gap-3">
        <div className="flex e items-center gap-2">
          <img src="/coin.png" className="w-5 h-5" alt="" />
          <h2>{credits}</h2>
        </div>

        <Link href="/create-new">
          <PrimaryButton className="bg-primary">+ Create New</PrimaryButton>
        </Link>
      </div>

      <div className="block lg:hidden">
        <MobileSidebar></MobileSidebar>
      </div>
    </div>
  );
};
export default Header;
