import Link from "next/link";
import { LIMIT } from "@/libs/constants";
import {
	ChevronFirst,
	ChevronLast,
	ChevronLeft,
	ChevronRight,
} from "lucide-react";

export const Pagination = ({
	totalCount,
	currentPage = 1,
	basePath,
	tagId,
}: {
	totalCount: number;
	currentPage: number;
	basePath: string;
	tagId?: string;
}) => {
	const totalPages = Math.ceil(totalCount / LIMIT);

	const createHref = (page: number) =>
		basePath === "category" && tagId
			? `/${basePath}/${tagId}/${page}`
			: `/${basePath}/${page}`;

	const isDisabled = (condition: boolean) =>
		condition ? "opacity-50 pointer-events-none" : "";

	return (
		<ul className="mt-20 flex items-start justify-center space-x-3 md:space-x-4">
			<li>
				<Link
					href={createHref(1)}
					className={`relative rounded-md bg-[#F6F3EA] p-2 px-5 text-sm shadow hover:opacity-70 md:p-2 md:px-5 md:text-lg ${isDisabled(
						currentPage === 1,
					)}`}
				>
					<ChevronFirst className="absolute top-1 right-2 bottom-0 md:top-auto md:right-2 md:bottom-[6px]" />
				</Link>
			</li>
			<li>
				<Link
					href={createHref(currentPage - 1)}
					className={`relative rounded-md bg-[#F6F3EA] p-2 px-5 text-sm shadow hover:opacity-70 md:p-2 md:px-5 md:text-lg ${isDisabled(
						currentPage === 1,
					)}`}
				>
					<ChevronLeft className="absolute top-1 right-2 bottom-0 md:top-auto md:right-2 md:bottom-[6px]" />
				</Link>
			</li>
			<li>
				<span className="rounded-md bg-[#F6F3EA] p-2 px-2 text-base shadow md:p-2 md:px-3 md:text-lg">
					{currentPage} / {totalPages}
				</span>
			</li>
			<li>
				<Link
					href={createHref(currentPage + 1)}
					className={`relative rounded-md bg-[#F6F3EA] p-2 px-5 text-sm shadow hover:opacity-70 md:p-2 md:px-5 md:text-lg ${isDisabled(
						currentPage === totalPages,
					)}`}
				>
					<ChevronRight className="absolute top-1 right-2 bottom-0 md:top-auto md:right-2 md:bottom-[6px]" />
				</Link>
			</li>
			<li>
				<Link
					href={createHref(totalPages)}
					className={`relative rounded-md bg-[#F6F3EA] p-2 px-5 text-sm shadow hover:opacity-70 md:p-2 md:px-5 md:text-lg ${isDisabled(
						currentPage === totalPages,
					)}`}
				>
					<ChevronLast className="absolute top-1 right-2 md:top-auto md:right-2 md:bottom-[6px]" />
				</Link>
			</li>
		</ul>
	);
};
