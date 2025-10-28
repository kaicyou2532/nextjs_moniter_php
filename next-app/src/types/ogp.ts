// types/ogp.ts
export interface OgpData {
	title: string;
	description?: string;
	image?: string;
	siteName?: string;
	url: string;
}

export interface OgpResponse {
	success: boolean;
	ogp?: OgpData;
	error?: string;
}
