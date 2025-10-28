import Image from "next/image";

type Props = {
	image: string;
	title: string;
	text: React.ReactNode;
	className?: string;
};

const JobIntroduction = ({ image, title, text, className }: Props) => {
	return (
		<div className={`${className} flex flex-col rounded-md bg-white`}>
			{/* 画像の表示 */}
			<Image
				src={image}
				alt={title}
				width={400}
				height={300}
				layout="responsive"
				objectFit="cover"
				priority
				className="mb-4 aspect-[16/9] w-full rounded-md object-cover"
			/>

			{/* テキストエリア */}
			<div className="text-left">
				<div className="mb-2 font-bold text-xl">{title}</div>
				<div className="text-base">{text}</div>
			</div>
		</div>
	);
};

export default JobIntroduction;
