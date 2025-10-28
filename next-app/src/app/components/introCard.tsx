import Image from "next/image";
import Link from "next/link";
import {
	Accordion,
	AccordionContent,
	AccordionItem,
	AccordionTrigger,
} from "@/components/ui/accordion";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faArrowUpRightFromSquare } from "@fortawesome/free-solid-svg-icons";

type Props = {
	image: string;
	title: string;
	url?: string;
	time: string | React.ReactNode;
	text: React.ReactNode;
	accordionText?: React.ReactNode;
};

const introCard = ({ image, title, time, text, url, accordionText }: Props) => {
	return (
		<div className="flex flex-col items-start bg-white">
			<Image
				src={image}
				alt={title}
				width={400}
				height={300}
				className="mb-4 aspect-[16/9] w-full rounded object-cover"
			/>

			<div className="mb-3 font-bold text-xl">{title}</div>

			<div className="mt-1 w-full rounded bg-muted p-2">
				<div className="text-base text-black">{time}</div>
			</div>
			<div className="mt-2 text-base">{text}</div>

			<Accordion type="multiple" className="w-full">
				<AccordionItem value="item-1">
					<AccordionTrigger>利用方法</AccordionTrigger>
					<AccordionContent>
						{url ? (
							<div className="flex text-base">
								<Link
									target="_blank"
									href={url}
									className="flex items-center gap-1 text-blue-500 hover:opacity-70"
								>
									こちら
									<FontAwesomeIcon
										icon={faArrowUpRightFromSquare}
										className="size-4 text-black"
									/>
								</Link>
								をご確認ください。
							</div>
						) : (
							<div className="text-base">申請不要でご利用いただけます。</div>
						)}
					</AccordionContent>
				</AccordionItem>
				{accordionText && (
					<AccordionItem value="item-2">
						<AccordionTrigger>注意事項</AccordionTrigger>
						<AccordionContent>{accordionText}</AccordionContent>
					</AccordionItem>
				)}
			</Accordion>
		</div>
	);
};

export default introCard;
