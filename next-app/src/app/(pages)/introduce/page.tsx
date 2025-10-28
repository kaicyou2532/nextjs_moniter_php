import Heading from "@/app/components/heading";
import SwitchCampus from "../../components/switchCampus";
import type { Metadata } from "next";

export const metadata: Metadata = {
	title: "施設紹介",
	description: "AIM Commons 相模原の施設について詳しくご紹介します",
};

const moviePage = async () => {
	return (
		<div className="py-[75px] text-[20px] text-black leading-10">
			<Heading
				engTitle="FACILITIES"
				jpTitle="施設紹介"
				abst="設備について詳しくご紹介します"
			/>
			<div className="min-h-screen bg-[#F0EBDC]" id="jumpToMap">
				<SwitchCampus />
			</div>
		</div>
	);
};

export default moviePage;
