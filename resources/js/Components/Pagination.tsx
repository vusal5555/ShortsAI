import { Link } from "@inertiajs/react";

export default function Pagination({ links }: any) {
  return (
    <nav className="text-center py-5">
      {links.map((link: any) => (
        <Link
          preserveScroll
          href={link.url || ""}
          key={link.label}
          className={
            "inline-block py-2 px-3 rounded-lg text-primary-200 text-md " +
            (link.active ? "bg-primary text-white " : " ") +
            (!link.url
              ? "!text-gray-500 cursor-not-allowed "
              : "hover:bg-primary hover:text-white")
          }
          dangerouslySetInnerHTML={{ __html: link.label }}
        ></Link>
      ))}
    </nav>
  );
}
