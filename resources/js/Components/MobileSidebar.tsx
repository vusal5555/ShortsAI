import { Sheet, SheetContent, SheetTrigger } from "@/Components/ui/sheet";
import menuOption from "@/utils/navLinks";
import { Link } from "@inertiajs/react";
import { Menu } from "lucide-react";

type Props = {};

const MobileSidebar = (props: Props) => {
  const path = route().current();
  return (
    <Sheet>
      <SheetTrigger>
        <Menu className="text-black" />
      </SheetTrigger>
      <SheetContent side="left">
        <div className="w-full h-screen">
          <div className="grid gap-2">
            <Link href="/dashboard ">
              <h2 className="font-bold text-3xl text-primary mb-10">
                Shorts AI
              </h2>
            </Link>
            {menuOption.map((item, index) => (
              <Link href={item.link} key={index}>
                <div
                  className={`flex items-center gap-3 p-4 hover:bg-primary hover:text-white rounded-md ${
                    path === item.link ? "bg-primary text-white" : ""
                  }`}
                >
                  <item.icon></item.icon>
                  <h2>{item.name}</h2>
                </div>
              </Link>
            ))}
          </div>
        </div>
      </SheetContent>
    </Sheet>
  );
};

export default MobileSidebar;
