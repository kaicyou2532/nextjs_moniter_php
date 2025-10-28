import { getBlogDetail } from "@/libs/microcms";
import { draftMode, cookies } from "next/headers"; // cookiesをインポート
import { redirect } from "next/navigation";

export async function GET(request: Request) {
	const { searchParams } = new URL(request.url);
	const secret = searchParams.get("secret");
	const contentId = searchParams.get("contentId");
	const draftKey = searchParams.get("draftKey"); // 実際のdraftKey

	// シークレットキーの検証
	if (secret !== process.env.MICROCMS_PREVIEW_SECRET || !contentId) {
		return new Response("Invalid token", { status: 401 });
	}

	// 記事の存在確認 (draftKeyを使って下書きコンテンツをフェッチ)
	const article = await getBlogDetail(contentId, {
		draftKey: draftKey || undefined,
	}).catch(() => null);

	if (!article) {
		return new Response("Invalid article", { status: 401 });
	}

	// Draft Modeを有効化
	const draft = await draftMode();
	draft.enable();

	// ★追加: 実際のdraftKeyをCookieに保存
	cookies().set("microcms-draft-key", draftKey as string, {
		path: "/",
		httpOnly: true, // JavaScriptからアクセス不可にする
		secure: process.env.NODE_ENV === "production", // HTTPSでのみ送信
		maxAge: 60 * 60 * 24, // 例: 1日間有効
	});

	// draftKeyをURLパラメータとして保持せずリダイレクト
	redirect(`/blog/article/${contentId}`);
}
