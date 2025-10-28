import type { Metadata } from "next";
import "./globals.css";
import Header from "./components/header";
import Footer from "./components/footer";
import { BIZ_UDPGothic } from "next/font/google";
import { GoogleAnalytics } from "@next/third-parties/google";

export const metadata: Metadata = {
	title: {
		template: "%s | AIM Commons 相模原",
		default: "AIM Commons 相模原 | 青山学院大学情報メディアセンター",
	},
	description:
		"AIM Commons 相模原とは、青山学院大学情報メデイアセンターに属している、ITに特化した学習用の施設及びそれに付帯するサービスの総称です。",
	keywords: [
		"青山学院大学",
		"青学",
		"ラーニングコモンズ",
		"ラーコモ",
		"AIM Commons 相模原",
		"パソコン",
		"勉強スペース",
		"学習室",
		"自習",
		"貸出",
		"ワークショップ",
		"YouTube",
		"採用",
		"お知らせ",
		"技術",
	],
	openGraph: {
		title: "AIM Commons 相模原 | 青山学院大学情報メディアセンター",
		description:
			"AIM Commons 相模原とは、青山学院大学情報メデイアセンターに属している、ITに特化した学習用の施設及びそれに付帯するサービスの総称です。",
		url: "https://commons.aim.aoyama.ac.jp",
		siteName: "AIM Commons 相模原 | 青山学院大学情報メディアセンター",
		images: [
			{
				width: "200",
				height: "100",
				url: "https://commons.aim.aoyama.ac.jp/images/logo/nav_logo.svg",
			},
		],
		locale: "jp",
		type: "article",
	},
};

const udp = BIZ_UDPGothic({ subsets: ["latin"], weight: ["400", "700"] });

export default function RootLayout({
	children,
}: Readonly<{
	children: React.ReactNode;
}>) {
	const gaId = process.env.GA_ID || "";

	return (
		<html lang="ja">
			<head>
				<GoogleAnalytics gaId={gaId} />
				<link
					rel="stylesheet"
					href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&icon_names=link&display=optional"
				/>
			</head>
			<body className={`${udp.className} flex min-h-screen flex-col`}>
				<Header />
				<div className="flex-grow bg-[#F0EBDC]">
					<div className="mx-[5vw] max-w-full pb-[30px]">
						<div
							id="side_line"
							className="writing-mode-vertical-rl fixed font-semibold text-xs tracking-wide"
						>
							<div className="left_side fixed top-[280px] left-[7px] ml-1 hidden [writing-mode:vertical-rl] lg:block">
								<p>AIM Commons Sagamihara</p>
							</div>
							<div className="right_side fixed top-[280px] right-[7px] mr-1 hidden [writing-mode:vertical-rl] lg:block">
								<p>AIM Commons Sagamihara</p>
							</div>
						</div>
						{children}
					</div>
				</div>
				<Footer />
			</body>
		</html>
	);
}
