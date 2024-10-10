import {
  LayoutDashboard,
  FileVideo,
  ShieldPlus,
  CircleUser,
} from "lucide-react";

const menuOption = [
  {
    id: 1,
    name: "Dashboard",
    icon: LayoutDashboard,
    link: "/dashboard",
  },
  {
    id: 2,
    name: "Create New",
    icon: FileVideo,
    link: "/create-new",
  },
  {
    id: 3,
    name: "Upgrade",
    icon: ShieldPlus,
    link: "/upgrade",
  },
  {
    id: 4,
    name: "Account",
    icon: CircleUser,
    link: "/logout",
  },
];
export default menuOption;
