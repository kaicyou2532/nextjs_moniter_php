import { faUser } from "@fortawesome/free-solid-svg-icons";
import { faCouch } from "@fortawesome/free-solid-svg-icons";
import { FontAwesomeIcon } from "@fortawesome/react-fontawesome";
import { formatInTimeZone, toZonedTime } from "date-fns-tz";
import { UserCountLayout } from "./userCountLayout";

type apiResponse = {
	createdAt: string;
	contents: {
		countList: {
			sofa_backleft: number;
			pc: number;
			tatami: number;
			cafe: number;
			highchair: number;
			movable: number;
			sofa_backright: number;
			sofa_frontleft: number;
			sofa_frontright: number;
			vf_mac: number;
			vf_win: number;
			silent: number;
		};
		comment: string;
	};
}[];

export default async function UserDisplay() {
	const now = new Date();
	const timeZone = "Asia/Tokyo";

	const japanTime = toZonedTime(now, timeZone);

	const hour = japanTime.getHours();
	const day = japanTime.getDay();
	const year = japanTime.getFullYear();
	const month = japanTime.getMonth();
	const date = japanTime.getDate();
	console.log("今日: ", year, month, date);

	let total: number | "---";
	let note: string | undefined;
	let congestion = "";
	let sofaCongestion = "";
	let icon = 0;
	let sofaCount = 0;

	if (day >= 1 && day <= 5 && hour >= 10 && hour < 17) {
		//土日と時間外は確定で除外
		const url =
			"https://script.google.com/macros/s/AKfycbzANLahldgD9yJ2Rf2xxN1sHUNtgXAeBEmjkQBPVQSdSs5gRQaY0CuPUwE5CeDSxrYH-Q/exec?limit=1";
		const res = await fetch(url, { cache: "no-store" });
		const data: apiResponse = await res.json();
		console.log(data[0].contents);

		total = Object.values(data[0].contents.countList).reduce(
			(sum, v) => sum + v,
			0,
		);
		const latestYear = new Date(data[0].createdAt).getFullYear();
		const latestMonth = new Date(data[0].createdAt).getMonth();
		const latestDate = new Date(data[0].createdAt).getDate();
		console.log("最新: ", latestYear, latestMonth, latestDate);

		if (
			latestYear === year &&
			latestMonth === month &&
			latestDate === date &&
			typeof total === "number"
		) {
			//取得した最新データの日付が今日じゃなければ除外
			note = formatInTimeZone(
				new Date(data[0].createdAt),
				timeZone,
				"M/d H:mm",
			);
			if (total >= 20) {
				congestion = "多数";
				icon = 4;
			} else if (total >= 15) {
				congestion = "やや多数";
				icon = 3;
			} else if (total >= 10) {
				congestion = "やや少数";
				icon = 2;
			} else if (total >= 1) {
				congestion = "少数";
				icon = 1;
			} else if (total === 0) {
				congestion = "少数";
				icon = 0;
			}
			if (data[0].contents.countList.sofa_backleft !== 0) {
				sofaCount += 1;
			}
			if (data[0].contents.countList.sofa_backright !== 0) {
				sofaCount += 1;
			}
			if (data[0].contents.countList.sofa_frontleft !== 0) {
				sofaCount += 1;
			}
			if (data[0].contents.countList.sofa_frontright !== 0) {
				sofaCount += 1;
			}
			if (sofaCount !== 4) {
				sofaCongestion = "空きあり";
			} else {
				sofaCongestion = "空きなし";
			}
		} else {
			console.log("今日じゃない");
			total = "---";
			note = "集計時間外";
		}
	} else {
		total = "---";
		note = "集計時間外";
	}

	return (
		<UserCountLayout note={note}>
			<div className="flex items-baseline justify-center">
				<span className="font-bold text-4xl">
					{total === undefined ? "—--" : total}
				</span>
				<span className="text-lg">人</span>
			</div>
			<div className="flex items-center gap-2">
				{total !== "---" && (
					<>
						<div className="flex flex-col gap-2">
							<div className="flex gap-1">
								<FontAwesomeIcon
									icon={faUser}
									className={`size-4 ${
										icon > 0 ? "text-black" : "text-gray-400"
									}`}
								/>
								<FontAwesomeIcon
									icon={faUser}
									className={`size-4 ${
										icon > 1 ? "text-black" : "text-gray-400"
									}`}
								/>
								<FontAwesomeIcon
									icon={faUser}
									className={`size-4 ${
										icon > 2 ? "text-black" : "text-gray-400"
									}`}
								/>
								<FontAwesomeIcon
									icon={faUser}
									className={`size-4 ${
										icon > 3 ? "text-black" : "text-gray-400"
									}`}
								/>
							</div>
							<div className="flex gap-1">
								<FontAwesomeIcon
									icon={faCouch}
									className={`size-4 ${
										sofaCount > 0 ? "text-black" : "text-gray-400"
									}`}
								/>
								<FontAwesomeIcon
									icon={faCouch}
									className={`size-4 ${
										sofaCount > 1 ? "text-black" : "text-gray-400"
									}`}
								/>
								<FontAwesomeIcon
									icon={faCouch}
									className={`size-4 ${
										sofaCount > 2 ? "text-black" : "text-gray-400"
									}`}
								/>
								<FontAwesomeIcon
									icon={faCouch}
									className={`size-4 ${
										sofaCount > 3 ? "text-black" : "text-gray-400"
									}`}
								/>
							</div>
						</div>
						<div className="text-left">
							<p>利用者{congestion}</p>
							<p>ソファ{sofaCongestion}</p>
						</div>
					</>
				)}
			</div>
		</UserCountLayout>
	);
}
