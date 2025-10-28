import type {
	ArticleType,
	ArticleWithSourceType,
	TagType,
} from "@/types/microcms";
import { createClient } from "microcms-js-sdk";
import type { MicroCMSQueries } from "microcms-js-sdk";
import { LIMIT } from "./constants";

if (!process.env.MICROCMS_SERVICE_DOMAIN) {
	throw new Error("MICROCMS_SERVICE_DOMAIN is required");
}

if (!process.env.MICROCMS_API_KEY) {
	throw new Error("MICROCMS_API_KEY is required");
}

// API取得用のクライアントを作成
export const client = createClient({
	serviceDomain: process.env.MICROCMS_SERVICE_DOMAIN,
	apiKey: process.env.MICROCMS_API_KEY,
});

// お知らせ一覧を取得
export const getInfoList = async (queries?: MicroCMSQueries) => {
	const listData = await client.getList<ArticleType>({
		endpoint: "posts",
		queries,
	});

	return listData;
};

// お知らせの詳細を取得
export const getInfoDetail = async (
	contentId: string,
	queries?: MicroCMSQueries,
) => {
	try {
		const detailData = await client.get<ArticleType>({
			endpoint: "posts",
			contentId,
			queries,
		});

		// console.log("Fetched detail data:", detailData); // デバッグ用

		if (!detailData) {
			console.error("No data found for contentId:", contentId);
			return null;
		}

		return detailData;
	} catch (error) {
		console.error("Error fetching detail:", error);
		return null;
	}
};

// ブログ一覧を取得
export const getBlogList = async (queries?: MicroCMSQueries) => {
	const listData = await client.getList<ArticleType>({
		endpoint: "techblogs",
		queries,
	});

	return listData;
};

// ブログの詳細を取得
export const getBlogDetail = async (
	contentId: string,
	queries?: MicroCMSQueries,
) => {
	try {
		const detailData = await client.get<ArticleType>({
			endpoint: "techblogs",
			contentId,
			queries,
		});

		// console.log("Fetched detail data:", detailData); // デバッグ用

		if (!detailData) {
			console.error("No data found for contentId:", contentId);
			return null;
		}

		return detailData;
	} catch (error) {
		console.error("Error fetching detail:", error);
		return null;
	}
};

// タグの一覧を取得
export const getTagList = async () => {
	let offset = 0;
	const limit = 50;
	let listData: TagType[] = [];
	let fetchedData = null;

	do {
		const queries = { limit, offset };
		fetchedData = await client.getList<TagType>({
			endpoint: "tags",
			queries: {
				...queries,
				fields: "id,title,path",
			},
		});
		listData = listData.concat(fetchedData.contents);
		offset += limit; // Update the offset for the next batch
	} while (fetchedData && listData.length < fetchedData.totalCount);

	return listData;
};

//タグ一覧を取得
// タグー記事一覧を取得する関数
export const getCategoryArticleList = async (queries?: MicroCMSQueries) => {
	// console.log("Fetching articles for tagPath:", tagPath);

	const allArticles: ArticleWithSourceType[] = [];
	const limit = 50; //(後任者へ：そのタグを含む記事をお知らせとブログの両方から全て取得し、その後ページネーションに切り分ける実装です。記事数が増えると、タグページの読み込みが遅くなる可能性があります。)
	let total = 0;
	const filters = queries?.filters;

	try {
		// posts と techblogs の記事を逐次取得
		const fetchArticles = async (
			endpoint: "posts" | "techblogs",
			source: "info" | "blog",
		) => {
			let offset = 0;
			while (true) {
				// console.log(`Fetching ${endpoint} articles with offset ${offset}`);
				const data = await client.getList<ArticleType>({
					endpoint,
					queries: { limit, offset, filters },
				});

				total = data.totalCount;
				allArticles.push(
					...data.contents.map(
						(article): ArticleWithSourceType => ({
							...article,
							source, // 明示的に "info" または "blog" を指定
						}),
					),
				);

				if (offset + limit >= total) {
					break; // 全件取得済み
				}
				offset += limit; // 次のページに進む
			}
		};

		// posts の記事取得
		await fetchArticles("posts", "info");
		// techblogs の記事取得
		await fetchArticles("techblogs", "blog");

		// console.log("All fetched articles:", allArticles);

		// ソートとページネーションの適用
		allArticles.sort((a, b) => {
			const dateA = new Date(a.publishedAt ?? a.updatedAt).getTime();
			const dateB = new Date(b.publishedAt ?? b.updatedAt).getTime();
			return dateB - dateA;
		});

		const totalCount = allArticles.length;
		const offsetQuery = queries?.offset || 0;
		const limitQuery = queries?.limit || LIMIT;
		const paginatedArticles = allArticles.slice(
			offsetQuery,
			offsetQuery + limitQuery,
		);

		return {
			contents: paginatedArticles,
			totalCount,
		};
	} catch (err) {
		console.error("Error fetching articles:", err);
		throw err;
	}
};
