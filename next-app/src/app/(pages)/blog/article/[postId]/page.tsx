import { notFound } from "next/navigation";
import { getBlogDetail, getBlogList } from "../../../../../libs/microcms";
import Article from "@/app/components/article";
import Heading from "@/app/components/heading";
import type { Metadata } from "next";
import { draftMode, cookies } from "next/headers"; // cookiesをインポート
import { Button } from "@/components/ui/button";
import Link from "next/link";

export async function generateMetadata({
	params,
}: {
	params: { postId: string };
}): Promise<Metadata> {
	console.log("Generating metadata for:", params);

	const { isEnabled } = draftMode(); // draftModeが有効かどうかを確認

	// ★追加: Cookieから実際のdraftKeyを読み込む
	const actualDraftKey = cookies().get("microcms-draft-key")?.value;

	const blog = await getBlogDetail(params.postId, {
		// draftModeが有効で、かつCookieにdraftKeyが存在する場合のみその値を渡す
		draftKey: isEnabled && actualDraftKey ? actualDraftKey : undefined,
	});

	if (!blog) {
		return {
			title: "記事が見つかりません",
			description: "お探しの記事は存在しないか、削除された可能性があります。",
		};
	}

	const metadata: Metadata = {
		title: `${blog.title}`,
		description: `${blog.title}に関する記事です`,
		openGraph: {
			title: `${blog.title}`,
			description: `${blog.title}に関する記事です`,
			type: "article",
			url: `https://commons.aim.aoyama.ac.jp/blog/${params.postId}`,
			siteName: "AIM Commons 相模原",
			images: {
				url: "https://commons.aim.aoyama.ac.jp/images/logo/logo_commons.jpeg",
				width: 1200,
				height: 630,
				alt: blog.title,
			},
		},
		twitter: {
			card: "summary_large_image",
			title: `${blog.title}`,
			description: `${blog.title}に関する記事です`,
			site: "@AIM Commons 相模原",
			images: {
				url: "https://commons.aim.aoyama.ac.jp/images/logo/logo_commons.jpeg",
				width: 1200,
				height: 630,
				alt: blog.title,
			},
		},
	};

	if (isEnabled) {
		metadata.robots = {
			index: false,
		};
	}

	console.log("Generated metadata:", metadata);
	return metadata;
}

export async function generateStaticParams() {
	const { contents } = await getBlogList();

	const paths = contents
		.filter((post) => post.id)
		.map((post) => ({
			postId: post.id.toString(),
		}));

	return paths;
}

export default async function StaticDetailPage({
	params: { postId },
}: {
	params: { postId: string };
}) {
	const { isEnabled } = draftMode(); // draftModeが有効かどうかを確認

	// ★追加: Cookieから実際のdraftKeyを読み込む
	const actualDraftKey = cookies().get("microcms-draft-key")?.value;

	// draftModeが有効で、かつCookieにdraftKeyが存在する場合のみその値を渡す
	const article = await getBlogDetail(postId, {
		draftKey: isEnabled && actualDraftKey ? actualDraftKey : undefined,
	});
	console.log(article);

	if (!article) {
		console.error("Post not found:", postId);
		notFound();
	}

	return (
		<>
			{isEnabled && (
				<>
					<div className="text-lg">
						プレビューモード中 - これは下書きのプレビューです
					</div>
					<Link href="/api/disable_draft?redirect=/blog/1">
						<Button>プレビューモードを解除</Button>
					</Link>
				</>
			)}
			<div className="py-[75px] text-[20px] text-black leading-10">
				<Heading engTitle="INFO" jpTitle="お知らせ" />
				<Article content={article} />
			</div>
		</>
	);
}
