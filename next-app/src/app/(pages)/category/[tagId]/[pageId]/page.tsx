import Articlelist from "@/app/components/articlelist";
import { LIMIT } from "@/libs/constants";
import { getCategoryArticleList, getTagList } from "@/libs/microcms";
import Tag from "@/app/components/tag";
import Heading from "@/app/components/heading";
import { notFound } from "next/navigation";

export async function generateMetadata({
	params: { tagId },
}: {
	params: { tagId: string };
}) {
	const tagListResponse = await getTagList();

	if (!tagListResponse) {
		return {
			title: "業務ブログ",
			description: "AIM Commons 相模原スタッフからの発信です",
		};
	}

	const currentTag = tagListResponse.find(
		(tag: { path: string }) => tag.path === tagId,
	);

	if (currentTag) {
		const tagName = currentTag.title;
		return {
			title: tagName,
			description: `${tagName}に関連する記事一覧です`,
		};
	}
}

export async function generateStaticParams() {
	const tagContents = await getTagList();

	if (!tagContents) {
		console.error("Failed to fetch tag list for static params");
		return [];
	}

	const paths = [];

	for (const tag of tagContents) {
		const articlesListResponse = await getCategoryArticleList({
			filters: `tags[contains]${tag.id}`,
		});

		if (!articlesListResponse) {
			console.warn(`No articles found for tag ${tag.path}`);
			continue;
		}

		const { totalCount } = articlesListResponse;
		const totalPages = Math.ceil(totalCount / LIMIT);

		for (let page = 1; page <= totalPages; page++) {
			paths.push({ tagId: tag.path, pageId: page.toString() });
		}
	}

	// console.log("Generated static paths:", paths);
	return paths;
}

export default async function CategoryArticleList({
	params,
}: {
	params: { tagId: string; pageId: string };
}) {
	// console.log("Received params:", params); // params をログ出力
	const currentPage = Number.parseInt(params.pageId, 10);
	const tagPath = params.tagId;
	// console.log("Parsed params:", { currentPage, tagPath });

	// タグリスト取得
	const tagContents = await getTagList();

	const tag = tagContents.find((t) => t.path === tagPath);
	if (!tag) {
		console.error("Tag not found:", tagPath);
		return notFound();
	}

	const tagTitle = tag.title;
	const tagId = tag.id;

	// クエリの設定
	const initialQueries = {
		limit: LIMIT,
		offset: (currentPage - 1) * LIMIT,
		filters: `tags[contains]${tagId}`,
	};

	console.log("Initial queries:", initialQueries);

	// 記事リスト取得
	const articlesListResponse = await getCategoryArticleList(initialQueries);

	const { totalCount, contents } = articlesListResponse;
	// console.log("Fetched articles:", { totalCount, contents });

	// 最大ページ数を計算
	const maxPage = Math.ceil(totalCount / LIMIT);
	if (currentPage > maxPage || Number.isNaN(currentPage) || currentPage < 1) {
		console.error("Page exceeds maxPage:", { currentPage, maxPage });
		return (
			<div className="py-[75px] font-bold text-[20px] text-black leading-10">
				<Heading
					engTitle="ARTICLES"
					jpTitle={
						<>
							{tagTitle}
							<span className="mg:text-xl text-lg">に関連する記事</span>
						</>
					}
				/>
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
				engTitle="ARTICLES"
				jpTitle={
					<>
						{tagTitle}
						<span className="mg:text-xl text-lg">に関連する記事</span>
					</>
				}
			/>
			<Articlelist
				contents={contents}
				basePath="category"
				currentPage={currentPage}
				totalCount={totalCount}
				tagId={tagPath}
			/>
			<hr className="mt-8 border-[#d9ae4c] border-[1px]" />
			<div className="mt-2 ml-2">タグから探す</div>
			<div className="mt-2 ml-4">
				<Tag tags={tagContents} variant="card" />
			</div>
		</div>
	);
}
