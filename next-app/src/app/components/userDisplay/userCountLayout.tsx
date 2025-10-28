import { Card, CardContent, CardFooter } from "@/components/ui/card";
import Title from "../title";

type Props = {
	children: React.ReactNode;
	note?: string;
};

export function UserCountLayout({ children, note }: Props) {
	return (
		<Card className="border-0 p-6">
			<div className="mb-3 border-b text-center">
				<Title
					maintitle="現在の利用者数"
					subtitle="CURRENT USERS"
					notes={note}
				/>
			</div>
			<div className="h-12">
				<CardContent className="flex items-center justify-center gap-3 p-0">
					{children}
				</CardContent>
			</div>
			<CardFooter className="flex justify-center px-4 py-2">
				<p className="text-center text-xs">
					開室日10:00〜16:30の間で
					<span className="inline-block">30分おきに更新されます</span>
					
				</p>
			</CardFooter>
		</Card>
	);
}
