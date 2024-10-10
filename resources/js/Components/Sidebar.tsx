import menuOption from "@/utils/navLinks";
import { Link } from "@inertiajs/react";

const Sidebar = () => {
  const path = route().current();

  return (
    <div className="w-64 h-screen  p-5">
      <div className="grid gap-2">
        <Link href="/dashboard ">
          <h2 className="font-bold text-3xl text-primary mb-10">Shorts AI</h2>
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
  );
};
export default Sidebar;
