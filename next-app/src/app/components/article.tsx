import type { ArticleType } from "@/types/microcms";
import { format } from "date-fns";
import parse, {
	domToReact,
	Element,
	type HTMLReactParserOptions,
	type DOMNode,
} from "html-react-parser";
import Tag from "./tag";
import { Prism as SyntaxHighlighter } from "react-syntax-highlighter";
import { vscDarkPlus } from "react-syntax-highlighter/dist/esm/styles/prism";
import { LinkPreview } from "./link-preview";

export default function Article({ content }: { content: ArticleType }) {
	const options: HTMLReactParserOptions = {
		replace: (domNode): JSX.Element | string | undefined => {
			// コードブロックの処理
			if (
				domNode instanceof Element &&
				domNode.name === "pre" &&
				domNode.children.length === 1 &&
				domNode.children[0] instanceof Element &&
				domNode.children[0].name === "code"
			) {
				const codeElement = domNode.children[0] as Element;
				const codeContent = domToReact(codeElement.children as DOMNode[]);

				let codeText = "";
				if (typeof codeContent === "string") {
					codeText = codeContent;
				} else if (Array.isArray(codeContent)) {
					codeText = codeContent.join("");
				}

				let language = "";
				if (codeElement.attribs.class) {
					const match = codeElement.attribs.class.match(/language-(\w+)/);
					if (match) {
						language = match[1];
					}
				}

				try {
					const fileName = domNode.attribs?.["data-filename"] || "";

					return (
						<div className="code-block-wrapper">
							{fileName && (
								<div className="code-filename rounded-t-md bg-gray-800 px-4 py-2 text-sm text-white">
									{fileName}
								</div>
							)}
							<SyntaxHighlighter
								language={language || "typescript"}
								style={vscDarkPlus}
								className="hljs !my-0 overflow-auto rounded-md"
							>
								{codeText}
							</SyntaxHighlighter>
						</div>
					);
				} catch {
					return (
						<pre>
							<code className={codeElement.attribs.class || ""}>
								{codeContent}
							</code>
						</pre>
					);
				}
			}

			if (domNode instanceof Element && domNode.name === "p") {
				const children = domNode.children;
				const aIndex = children.findIndex(
					(child) =>
						child instanceof Element &&
						child.name === "a" &&
						child.attribs?.href,
				);

				if (aIndex !== -1) {
					const aElement = children[aIndex] as Element;
					const before = children.slice(0, aIndex);
					const after = children.slice(aIndex + 1);

					return (
						<>
							{before.length > 0 && (
								<p>{domToReact(before as DOMNode[], options)}</p>
							)}
							<div className="my-4">
								<LinkPreview url={aElement.attribs.href}>
									{domToReact(aElement.children as DOMNode[], options)}
								</LinkPreview>
							</div>
							{after.length > 0 && (
								<p>{domToReact(after as DOMNode[], options)}</p>
							)}
						</>
					);
				}
			}

			if (
				domNode instanceof Element &&
				domNode.name === "a" &&
				domNode.attribs?.href
			) {
				return (
					<LinkPreview url={domNode.attribs.href}>
						{domToReact(domNode.children as DOMNode[], options)}
					</LinkPreview>
				);
			}
		},
	};
	return (
		<div className="container mx-auto min-h-[60vh] rounded-xl bg-white py-[8%]">
			<div className="mx-[8%]">
				<h1 className="mb-4 font-bold text-2xl lg:text-3xl">{content.title}</h1>
				<div className="mb-2 block justify-between md:flex">
					<Tag tags={content.tags} variant="article" />
					<h2 className="text-right font-semibold text-gray-600 text-lg md:text-lg">
						{format(content.publishedAt || content.updatedAt, "yyyy.MM.dd")}
					</h2>
				</div>
				<hr className="mb-[5%] border-gray-600" />
			</div>
			<div className="prose mx-auto">{parse(content.body, options)}</div>
		</div>
	);
}
