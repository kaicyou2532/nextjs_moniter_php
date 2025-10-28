import Articlelist from "@/app/components/articlelist";
import { LIMIT } from "@/libs/constants";
import { getInfoList, getTagList } from "@/libs/microcms";
import Tag from "@/app/components/tag";
import type { Metadata } from "next";
import Heading from "@/app/components/heading";

export const metadata: Metadata = {
	title: "お知らせ",
	description: "AIM Commons 相模原からのお知らせ一覧です",
};

export async function generateStaticParams() {
	const queries = { limit: LIMIT, fields: "id" };
	const articlesListResponse = await getInfoList(queries);
	const { totalCount } = articlesListResponse;

	const range = (start: number, end: number) =>
		Array.from({ length: end - start + 1 }, (_, i) => start + i);

	const paths = range(1, Math.ceil(totalCount / LIMIT)).map((page) => ({
		current: page.toString(),
	}));

	return [...paths];
}

export default async function Info({ params }: { params: { pageId: string } }) {
	const currentPage = Number.parseInt(params.pageId, 10);

	const initialQueries = { limit: LIMIT, fields: "id" };
	const articlesListResponse = await getInfoList(initialQueries);
	const { totalCount } = articlesListResponse;

	const maxPage = Math.ceil(totalCount / LIMIT);

	const articlesListQueries = {
		limit: LIMIT,
		offset: (currentPage - 1) * LIMIT,
	};

	const articlePageResponse = await getInfoList(articlesListQueries);
	const tagContents = await getTagList();
	const { contents } = articlePageResponse;

	if (Number.isNaN(currentPage) || currentPage < 1 || currentPage > maxPage) {
		return (
			<div className="py-[75px] font-bold text-[20px] text-black leading-10">
				<Heading engTitle="NEWS" jpTitle="お知らせ" />
				<h1 className="mb-2 font-bold text-xl md:text-2xl">
					記事が見つかりません
				</h1>
				<p className="text-sm md:text-base">
					現在、このページに記事はありません。
				</p>
				<hr className="mt-8 border-[#d9ae4c] border-[1px]" />
				<div className="mt-2 ml-2">タグから探す</div>
				<div className="mt-2 ml-4">
					<Tag tags={tagContents} variant="card" />
				</div>
			</div>
		);
	}

	return (
		<div className="py-[75px] font-bold text-[20px] text-black leading-10">
			<Heading
				engTitle="NEWS"
				jpTitle="お知らせ"
				abst={<>AIM Commons 相模原からの<span className="inline-block">お知らせ一覧</span></>}
			/>
			<Articlelist
				contents={contents}
				basePath="info"
				currentPage={currentPage}
				totalCount={totalCount}
			/>
			<hr className="mt-8 border-[#d9ae4c] border-[1px]" />
			<div className="mt-2 ml-2">タグから探す</div>
			<div className="mt-2 mb-6 ml-4">
				<Tag tags={tagContents} variant="card" />
			</div>
		</div>
	);
}
