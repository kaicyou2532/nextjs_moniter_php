"use client";
import { useState } from "react";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { faChevronRight } from "@fortawesome/free-solid-svg-icons";

type Props = {
	longVideoIds: string[];
	shortVideoIds: string[];
};

export default function SwitchMovieType({
	longVideoIds,
	shortVideoIds,
}: Props) {
	const [filterType, setFilterType] = useState("shortsMovie");

	const handleFilterChange = (event: React.ChangeEvent<HTMLInputElement>) => {
		setFilterType(event.target.value);
	};

	const getFilteredVideos = () => {
		if (filterType === "longMovie") {
			return longVideoIds;
		}
		return shortVideoIds;
	};

	const filteredVideos = getFilteredVideos();

	return (
		<div className="w-full bg-[#F0EBDC]">
			<div className="my-8 flex justify-center space-x-6">
				<label
					className={`cursor-pointer rounded-lg border-2 px-10 py-6 ${
						filterType === "longMovie"
							? "border-black bg-white font-bold text-black text-sm sm:text-xl"
							: "border-black bg-white text-sm sm:text-xl"
					}`}
				>
					<input
						type="radio"
						name="filter"
						value="longMovie"
						checked={filterType === "longMovie"}
						onChange={handleFilterChange}
						className="hidden"
					/>
					<div className="flex items-center">
						<FontAwesomeIcon icon={faChevronRight} className="mr-4 size-3" />
						<span>動画</span>
					</div>
				</label>
				<label
					className={`cursor-pointer rounded-lg border-2 px-10 py-6 ${
						filterType === "shortsMovie"
							? "border-black bg-white font-bold text-sm sm:text-xl"
							: "border-black bg-white text-sm sm:text-xl"
					}`}
				>
					<input
						type="radio"
						name="filter"
						value="shortsMovie"
						checked={filterType === "shortsMovie"}
						onChange={handleFilterChange}
						className="hidden"
					/>
					<div className="flex items-center">
						<FontAwesomeIcon icon={faChevronRight} className="mr-4 size-3" />
						<span>ショート</span>
					</div>
				</label>
			</div>

			<div className="rounded-lg bg-white p-12 xl:p-16">
				<div
					className={`grid gap-12 xl:gap-16 ${
						filterType === "longMovie"
							? "grid-cols-1 lg:grid-cols-2"
							: "grid-cols-1 md:grid-cols-2 lg:grid-cols-3"
					}`}
				>
					{filteredVideos.map((videoId) => (
						<div key={`video-${videoId}`} className="flex">
							<iframe
								className={`w-full ${
									filterType === "longMovie" ? "aspect-video" : "aspect-[9/16]"
								}`}
								src={`https://www.youtube.com/embed/${videoId}`}
								title={`Video - ${videoId}`}
								allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
								allowFullScreen
							/>
						</div>
					))}
				</div>
			</div>
		</div>
	);
}
