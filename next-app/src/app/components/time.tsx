import Title from "./title";

type Location = {
	id: string;
	name: string;
	time: string;
};

type Props = {
	title: string;
	notes: string;
	subtitle: string;
	locations: Location[];
};

const Time = ({ title, notes, subtitle, locations }: Props) => {
	return (
		<div className="rounded-lg bg-white p-6">
			<div className="mb-4 border-b text-center">
				<Title maintitle={title} subtitle={subtitle} notes={notes} />
			</div>
			<div className="space-y-4 ">
				{locations.map((location) => (
					<div key={location.id} className="flex justify-between text-lg">
						<span className="text-gray-900">{location.name}</span>
						<span className="font-semibold text-2xl text-[#d9ae4c]">
							{location.time}
						</span>
					</div>
				))}
			</div>
		</div>
	);
};

export default Time;
