import { UserCountLayout } from "./userCountLayout";

export default function UserDisplayLoading() {
	return (
		<UserCountLayout>
			<div className="mb-3 h-7 w-7 animate-spin rounded-full border-4 border-[#d9ae4c] border-t-transparent" />
		</UserCountLayout>
	);
}
