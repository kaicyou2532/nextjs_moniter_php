import Link from "next/link";
import {
	FontAwesomeIcon,
	type FontAwesomeIconProps,
} from "@fortawesome/react-fontawesome";
import {
	faChevronRight,
	faArrowUpRightFromSquare,
} from "@fortawesome/free-solid-svg-icons";

type Props = {
	title: string | React.ReactNode;
	description: string | React.ReactNode;
	linkArray: {
		name: string;
		link: string;
		external: boolean;
	}[];
	icon: FontAwesomeIconProps["icon"];
};

export default function AbleCard({
	title,
	description,
	linkArray,
	icon,
}: Props) {
	return (
		<div className="flex h-[430px] flex-col gap-6 rounded-lg bg-white p-6">
			<h3 className="text-center font-bold text-[22px] xl:text-[24px]">
				{title}
			</h3>
			<div className="flex justify-center">
				<FontAwesomeIcon icon={icon} className="size-[120px]" />
			</div>
			<p className="mx-auto h-fit w-fit text-sm">{description}</p>
			<div className="mt-auto space-y-2">
				{linkArray.map((link) => (
					<Link
						href={link.link}
						className="flex items-center gap-2 rounded-md border border-black px-3 py-2 hover:opacity-70 "
						key={link.name}
						target={link.link.startsWith("https") ? "_blank" : undefined}
					>
						<FontAwesomeIcon icon={faChevronRight} className="size-3" />
						{link.name}
						{link.external && (
							<FontAwesomeIcon
								icon={faArrowUpRightFromSquare}
								className="size-4"
							/>
						)}
					</Link>
				))}
			</div>
		</div>
	);
}
