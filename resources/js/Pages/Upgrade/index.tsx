import MainLayout from "@/Layouts/MainLayout";
import { Head } from "@inertiajs/react";

type Props = {};

const index = (props: Props) => {
  return (
    <>
      <Head title="Upgrade"></Head>
      <MainLayout>
        <div className="text-black mt-20">Upgrade</div>
      </MainLayout>
    </>
  );
};

export default index;
