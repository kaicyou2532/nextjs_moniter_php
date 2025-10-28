import type { ArticleType } from "@/types/microcms";
import { format } from "date-fns";
import Image from "next/image";
import Tag from "./tag";

export default function Card({ content }: { content: ArticleType }) {
	return (
		<div className="relative mx-auto w-full max-w-[360px] flex-shrink-0 rounded-xl bg-[#F6F3EA] p-5 shadow hover:opacity-70">
			<Image
				src={content.thumbnail.url}
				className="aspect-[16/9] rounded-md object-cover"
				alt={`News Image ${content.id}`}
				width={360}
				height={200}
			/>
			<div className="pt-4">
				<h1 className="mb-2 line-clamp-2 h-[3.2rem] font-semibold text-[1.05rem] leading-7 md:text-[1.2rem]">
					{content.title}
				</h1>
				<div className="mb-2 font-semibold text-gray-600 text-sm">
					{format(
						new Date(content.publishedAt || content.updatedAt),
						"yyyy.MM.dd",
					)}
				</div>
				<div className="h-[1.5rem] overflow-hidden">
					<Tag tags={content.tags} variant="card" />
				</div>
			</div>
		</div>
	);
}
