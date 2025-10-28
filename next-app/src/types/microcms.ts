import type { MicroCMSDate, MicroCMSImage } from "microcms-js-sdk";

export type TagType = {
	id: string;
	title: string;
	path: string;
} & MicroCMSDate;

export type ArticleType = {
	id: string;
	title: string;
	body: string;
	tags: TagType[];
	thumbnail: MicroCMSImage;
} & MicroCMSDate;

export type ArticleWithSourceType = ArticleType & {
	source: "blog" | "info";
};
