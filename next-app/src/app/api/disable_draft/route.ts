import { draftMode } from "next/headers";
import { redirect } from "next/navigation";

export async function GET(request: Request) {
	const { searchParams } = new URL(request.url);
	console.log(searchParams);
	const redirectUrl = searchParams.get("redirect") || "/";

	const draft = await draftMode();
	draft.disable();

	redirect(redirectUrl);
}
