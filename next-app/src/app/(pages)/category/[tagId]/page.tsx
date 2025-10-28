import { getTagList } from "@/libs/microcms";
import { redirect } from "next/navigation";

export async function generateStaticParams() {
	const contents = await getTagList();

	const paths = contents
		.filter((category) => category.path)
		.map((category) => ({
			categoryPath: category.path.toString(),
		}));

	return paths;
}

export default function Page({
	params: { tagId },
}: { params: { tagId: string } }) {
	redirect(`/category/${tagId}/1`);
}
