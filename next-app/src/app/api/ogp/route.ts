// app/api/ogp/route.ts
import { type NextRequest, NextResponse } from "next/server";
import { load } from "cheerio";
import type { OgpResponse } from "@/types/ogp";

export async function GET(
	request: NextRequest,
): Promise<NextResponse<OgpResponse>> {
	const url = request.nextUrl.searchParams.get("url");

	if (!url) {
		return NextResponse.json(
			{
				success: false,
				error: "URL is required",
			},
			{ status: 400 },
		);
	}

	try {
		const response = await fetch(url);
		const html = await response.text();
		const $ = load(html);

		// OGPデータの抽出
		const ogpData = {
			title:
				$('meta[property="og:title"]').attr("content") ||
				$("title").text() ||
				url,
			description:
				$('meta[property="og:description"]').attr("content") ||
				$('meta[name="description"]').attr("content"),
			image: $('meta[property="og:image"]').attr("content"),
			siteName: $('meta[property="og:site_name"]').attr("content"),
			url: url,
		};

		return NextResponse.json({ success: true, ogp: ogpData });
	} catch (error) {
		const errorMessage =
			error instanceof Error ? error.message : "Unknown error";
		return NextResponse.json(
			{ success: false, error: errorMessage },
			{ status: 500 },
		);
	}
}
