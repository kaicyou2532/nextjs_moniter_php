import Image from "next/image";

type Props = {
	image: string;
	text: string | React.ReactNode;
	note?: string;
};

const Rule = ({ image, text, note }: Props) => {
	return (
		<div className="mb-4 flex w-full flex-col items-center bg-white p-2 sm:p-6">
			<div className="h-[250px] w-[250px]">
				<Image src={image} alt="禁止" width={250} height={250} />
			</div>
			<div className="mt-10 text-lg leading-5">{text}</div>
			<div className="mt-3 text-[#8C8C8C] text-base leading-5">{note}</div>
		</div>
	);
};

export default Rule;
