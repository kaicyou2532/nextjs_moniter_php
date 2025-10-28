"use client";

import styled from "styled-components";
import { Swiper, SwiperSlide } from "swiper/react";
import { Navigation, Pagination, A11y, Autoplay } from "swiper/modules"; // Autoplayを追加
import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";
import "swiper/css/scrollbar";
import "swiper/css/autoplay"; // Autoplay用のCSSも追加（必要な場合）
import Image from "next/image";

const StyledSwiper = styled(Swiper)`
  width: 100%;
  margin-bottom: 2rem;

  .swiper-slide {
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    font-weight: bold;
  }
`;

const ClientSwiper = () => (
	<StyledSwiper
		modules={[Navigation, Pagination, A11y, Autoplay]} // Autoplayをモジュールに追加
		loop={true}
		autoplay={{ delay: 3000 }}
		spaceBetween={20}
		slidesPerView={1}
		pagination={{ clickable: true }}
		scrollbar={false}
	>
		<SwiperSlide>
			<Image
				src="/images/introduce/floor07.jpg"
				alt="Slide-1"
				width={800}
				height={450}
				className="aspect-[16/9] w-full object-cover md:aspect-[2/1] lg:aspect-[5/2]"
			/>
		</SwiperSlide>
		<SwiperSlide>
			<Image
				src="/images/introduce/floor01.jpg"
				alt="Slide-1"
				width={800}
				height={450}
				className="aspect-[16/9] w-full object-cover md:aspect-[2/1] lg:aspect-[5/2]"
			/>
		</SwiperSlide>
		<SwiperSlide>
			<Image
				src="/images/general/pc_rental.jpg"
				alt="Slide-1"
				width={800}
				height={450}
				className="aspect-[16/9] w-full object-cover md:aspect-[2/1] lg:aspect-[5/2]"
			/>
		</SwiperSlide>
	</StyledSwiper>
);

export default ClientSwiper;
