import { Button } from "@/components/ui/button";
import {
	Sheet,
	SheetContent,
	SheetHeader,
	SheetTrigger,
} from "@/components/ui/sheet";
import { cn } from "@/libs/utils";
import { faArrowUpRightFromSquare } from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { AlignJustify } from "lucide-react";
import Image from "next/image";
import Link from "next/link";

export default function Header() {
	return (
		<header className="h-[100px] border-b">
			<div className="container mx-auto flex h-full items-center justify-between px-[15px]">
				<Link href="/">
					<Image
						src="/images/logo/nav_logo.svg"
						width={200}
						height={100}
						alt="ロゴ"
					/>
				</Link>

				<div>
					<Sheet>
						<SheetTrigger asChild>
							<Button variant="outline">
								<AlignJustify />
							</Button>
						</SheetTrigger>
						<SheetContent className="max-h-screen overflow-y-auto pb-10">
							<a href="/" className="inline-block w-fit">
								<Image
									src="/images/logo/nav_logo.svg"
									width={200}
									height={100}
									className="block"
									alt="ロゴ"
								/>
							</a>
							<SheetHeader className="mb-[10px] h-[20px] border-b" />

							<p className="my-3 font-bold text-sm text-stone-400 sm:text-base ">
								AIM Commons 相模原を<span className="inline-block">初めて使う方へ</span>
							</p>
							<ul>
								<li className={cn("px-5 py-3 text-sm sm:text-base")}>
									<a href={"/introduce"}>施設紹介</a>
								</li>
								<li className={cn("px-5 py-3 text-sm sm:text-base")}>
									<a
										href={
											"https://docs.google.com/spreadsheets/d/1pGRuvjajI833WFWqME8QbjGkraUQzgZ-Fp241Tbu7I8/edit?gid=0#gid=0"
										}
										target="_blank"
										rel="noreferrer"
										className="flex items-center gap-1"
									>
										貸出機器一覧
										<FontAwesomeIcon
											icon={faArrowUpRightFromSquare}
											className="size-3 md:size-4"
										/>
									</a>
								</li>
							</ul>
							<SheetHeader className="mb-[10px] h-[20px] border-b" />
							<p className="my-3 font-bold text-sm text-stone-400 sm:text-base">
								AIM Commons 相模原を<span className="inline-block">使いこなしたい方へ</span>
							</p>
							<p className="my-2 ml-2 font-bold text-sm text-stone-400 sm:text-base">
								ワークショップ
							</p>
							<ul className="mb-1">
								<li className={cn("px-5 py-3 text-sm sm:text-base")}>
									<a
										href={
											"https://ima-sc.notion.site/7fd23df752674abb95261bdc54b3de28"
										}
										target="_blank"
										rel="noreferrer"
										className="flex items-center gap-1"
									>
										ワークショップ
										<FontAwesomeIcon
											icon={faArrowUpRightFromSquare}
											className="size-3 md:size-4"
										/>
									</a>
								</li>
							</ul>
							<p className="my-2 ml-2 font-bold text-sm text-stone-400 sm:text-base">
								AIM Commons 相模原からの<span className="inline-block">情報発信</span>
							</p>
							<ul>
								<li className={cn("px-5 py-3 text-sm sm:text-base")}>
									<a href={"/info"}>お知らせ</a>
								</li>
								<li className={cn("px-5 py-3 text-sm sm:text-base")}>
									<a href={"/movies"}>YouTube動画</a>
								</li>
								<li className={cn("px-5 py-3 text-sm sm:text-base")}>
									<a href={"/blog"}>業務ブログ</a>
								</li>
							</ul>
							<SheetHeader className="mb-[10px] h-[20px] border-b" />
							{/* biome-ignore lint/nursery/useSortedClasses: <explanation> */}
							<p className="my-3 font-bold text-stone-400 text-sm sm:text-base">
								もっとAIM Commons 相模原に<span className="inline-block">関わりたい方へ</span>
							</p>
							<ul>
								<li className={cn("px-5 py-3 text-sm sm:text-base")}>
									<a href={"/recruit"}>学生スタッフ採用</a>
								</li>
							</ul>
						</SheetContent>
					</Sheet>
				</div>
			</div>
		</header>
	);
}
