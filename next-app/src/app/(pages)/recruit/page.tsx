import Heading from "@/app/components/heading";
import JobIntroduction from "@/app/components/jobIntroduction";
import Image from "next/image";
import Link from "next/link";
import type { Metadata } from "next";

export const metadata: Metadata = {
	title: "学生スタッフ採用",
	description: "AIM Commons 相模原の採用情報を紹介します",
};

const jobIntroductionPage = () => {
	return (
		<div className="py-[75px] font-bold text-[20px] text-black leading-10">
			<Heading
				engTitle="RECRUIT"
				jpTitle="学生スタッフ採用"
				abst={<>AIM Commons 相模原で働く<span className="inline-block">学生スタッフの業務を紹介します</span></>}
			/>
			<div className="my-8 flex flex-col-reverse items-center justify-center gap-4 rounded-lg bg-white p-8 pb-[4%] lg:p-12 xl:flex-row">
				<div className="w-full xl:w-1/2">
					<Image
						src="/images/general/about.png"
						alt="AIMの説明画像"
						className="mb-4 h-auto w-full rounded-md"
						width={500}
						height={300}
						layout="responsive"
						objectFit="cover"
					/>
				</div>
				<div className="w-full xl:ml-4 xl:w-1/2">
					<div className="font-bold text-lg md:text-[26px]">
						<h1 className="mb-2 leading-7 md:leading-9">
							AIM Commons 相模原の<span className="inline-block">学生スタッフとは？</span>
						</h1>
						<div className="my-2 text-[#d9ae4c] text-sm">STUDENT STAFF</div>
					</div>
					<div className="space-y-4 font-normal text-base leading-loose">
						<p>
							AIM
							Commonsでは、一緒に楽しく働く学生スタッフを定期的に募集しています！
							<br />
							受付・貸出業務をはじめ、ワークショップの講師、情報技術業務、広報活動などの様々なことに学生ならではの視点でチャレンジできるのが魅力です。
							<br />
							「IT-A」とはまた違った AIM Commons 相模原
							ならではの仕事がたくさん！
						</p>
						<ul className="ml-4 list-disc">
							<li>募集対象: 青山学院大学の学部生・大学院生</li>
							<li>募集時期: 前期・後期 各1回</li>
							<li>選考方法: 書類選考＋面接</li>
						</ul>
						<p>
							詳細は、募集時期に
							<Link
								href="/info"
								className="text-[#d9ae4c] underline hover:opacity-70"
							>
								このサイト
							</Link>
							および学生ポータルでお知らせします。
							<br />
							一緒に楽しく業務に関わってみませんか？
						</p>
					</div>
				</div>
			</div>
			<div className="rounded-lg bg-white py-8 lg:py-12 xl:py-16">
				<div>
					<h3 className="mb-1 text-center text-2xl">業務紹介</h3>
					<div className="text-center font-bold text-[#d9ae4c] text-sm md:text-base">
						DUTIES
					</div>
				</div>
				<div className="my-8 flex flex-col gap-8 text-center">
					<div>
						レギュラー業務
						<p className="text-base text-gray-600">
							全ての学生スタッフが担当する<span className="inline-block">基本的な業務です</span>
						</p>
					</div>
					<div className="!py-0 grid grid-cols-1 gap-8 p-8 lg:grid-cols-2 lg:gap-12 lg:p-12 xl:grid-cols-3 xl:gap-[3%]">
						<JobIntroduction
							image="/images/general/pc_rental.jpg"
							title="PC貸出"
							text={
								<div className="font-normal">
									<p>
										学生がキャンパス内の自由な場所でPCを利用できるように、B422窓口と自動貸出ロッカーでノートPCの貸出を行っています。
									</p>
									<p>
										学生スタッフは、返却されたPCのメンテナンスを担当しています。
									</p>
								</div>
							}
						/>
						<JobIntroduction
							image="/images/general/equipment.jpg"
							title="機器貸出"
							text={
								<div className="font-normal">
									<p>
										学生および教員の教育・研究活動を支援するために、4Kビデオカメラや動画編集設備等の機器や設備の貸出をB422窓口でしています。
									</p>
									<p>
										学生スタッフは、これらの使い方を習得して、利用者のニーズに沿った機器を提案・貸出を行っています。
									</p>
								</div>
							}
						/>
						<JobIntroduction
							image="/images/recruit/job-workshop.jpg"
							title="ワークショップ"
							text={
								<div className="font-normal">
									<p>
										Adobeの画像・動画編集ソフトや、一眼レフカメラなどを体験できるワークショップを毎日実施しています。
									</p>
									<p>
										学生スタッフは、受講者をサポートする講師として活躍しています。
									</p>
									<p>
										講師になるためのフォローアップも充実しているので、自分のスキルも高めるチャンスです！
									</p>
								</div>
							}
						/>
					</div>
					<div>
						プロジェクト型業務
						<p className="text-base text-gray-600">
							自分の関心や得意分野を活かして<span className="inline-block">選択的に取り組む業務です</span>
						</p>
						<p className="text-base text-gray-600">
							兼任・新規立ち上げも可能です
						</p>
					</div>
					<div className="!py-0 grid grid-cols-1 gap-8 p-8 lg:grid-cols-2 lg:gap-12 lg:p-12 xl:grid-cols-3 xl:gap-[3%]">
						<JobIntroduction
							image="/images/recruit/job-commercial.webp"
							title="広報活動"
							text={
								<div className="font-normal">
									<p>
										AIM
										Commonsを多くの人に知ってもらい、利用者を増やすための広報活動を行っています。
									</p>
									<p>
										学生スタッフは、ポスターや紹介パンフレット、プロムナードに設置される看板を制作しています。
									</p>
									<p>
										完成物はB棟周辺周辺や食堂など、多くの学生が目にする場所に掲示されています。
									</p>
								</div>
							}
						/>
						<JobIntroduction
							image="/images/recruit/job-video.png"
							title="動画制作"
							text={
								<div className="font-normal">
									<p>
										青山学院大学の学生に向けた、YouTube動画の制作を行っています。
									</p>
									<p>
										学生スタッフは、動画編集だけにとどまらず、企画、ナレーター、出演者としても活躍しています。
									</p>
									<p>
										アイデアを存分に発揮して、AIM Commons 相模原をPRしましょう！
									</p>
								</div>
							}
						/>
						<JobIntroduction
							image="/images/recruit/job-system.png"
							title="システム開発"
							text={
								<div className="font-normal">
									<p>
										業務で用いるシステムや当ウェブサイトの新規開発・保守を行っています。
									</p>
									<p>
										学生スタッフは、WEB開発を中心に、モダンな技術を積極的に取り入れた開発を行なっています。
									</p>
									<p>
										プログラミングはもちろん、チーム開発の経験を積みたいという人におすすめです！
									</p>
								</div>
							}
						/>
					</div>
					<div>
						管理スタッフ業務
						<p className="text-base text-gray-600">
							学生スタッフをまとめる<span className="inline-block">管理スタッフが行う業務です</span>
						</p>
					</div>
					<div className="!py-0 grid grid-cols-1 gap-8 p-8 lg:grid-cols-2 lg:gap-12 lg:p-12 xl:grid-cols-3 xl:gap-[3%]">
						<JobIntroduction
							image="/images/recruit/job-admin.webp"
							title="マネジメント業務"
							text={
								<div className="font-normal">
									<p>
										レギュラー業務およびプロジェクト型業務を取りまとめる中で、プロジェクトマネジメントを実践することができます。
									</p>
									<p>
										リーダーシップを身に付けたい方や、リーダーとしての経験をお持ちの方には、特に適した業務です。
									</p>
								</div>
							}
						/>
						<JobIntroduction
							image="/images/recruit/job-recruit.webp"
							title="採用業務"
							text={
								<div className="font-normal">
									<p>AIM Commons 相模原で働く新たな仲間を見つける業務です。</p>
									<p>
										教員や他の管理スタッフと協力しながら、新人スタッフの選考に関わることができます。
									</p>
									<p>
										一般的なアルバイトではなかなかできない貴重な経験を積むことができます。
									</p>
								</div>
							}
						/>
					</div>
				</div>
			</div>
		</div>
	);
};

export default jobIntroductionPage;
