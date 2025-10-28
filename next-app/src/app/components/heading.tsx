type Props = {
	engTitle: string;
	jpTitle: string | React.ReactNode;
	abst?: string | React.ReactNode;
};

export default function Heading({ engTitle, jpTitle, abst }: Props) {
	return (
		<div className="mb-[4vh] flex flex-col gap-2 px-[13%] md:gap-4">
			<div className="text-center font-bold text-[#d9ae4c] text-sm md:text-base">
				{engTitle}
			</div>
			<div className="text-center font-semibold text-2xl text-black md:text-4xl">
				{jpTitle}
			</div>
			<div className="text-center font-bold text-gray-600 text-sm md:text-lg">
				{abst}
			</div>
		</div>
	);
}
