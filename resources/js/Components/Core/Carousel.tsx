import { Image } from "@/types";
import { useEffect, useState } from "react";

function Carousel({ images }: { images: Image[] }) {
    const [selectedImage, setSelectedImage] = useState<Image | null>(images[0] || null);

    // Update the selected image when the images list changes
    useEffect(() => {
        if (images.length > 0) {
            setSelectedImage(images[0]);
        }
    }, [images]);

    // Keyboard navigation for arrow keys
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === "ArrowRight") {
                const currentIndex = images.findIndex(image => image.id === selectedImage?.id);
                const nextIndex = (currentIndex + 1) % images.length;
                setSelectedImage(images[nextIndex]);
            } else if (event.key === "ArrowLeft") {
                const currentIndex = images.findIndex(image => image.id === selectedImage?.id);
                const prevIndex = (currentIndex - 1 + images.length) % images.length;
                setSelectedImage(images[prevIndex]);
            }
        };

        window.addEventListener("keydown", handleKeyDown);

        return () => {
            window.removeEventListener("keydown", handleKeyDown);
        };
    }, [selectedImage, images]);

    // Autoplay functionality
    useEffect(() => {
        const interval = setInterval(() => {
            const currentIndex = images.findIndex(image => image.id === selectedImage?.id);
            const nextIndex = (currentIndex + 1) % images.length;
            setSelectedImage(images[nextIndex]);
        }, 5000); // Change every 5 seconds

        return () => clearInterval(interval);
    }, [selectedImage, images]);

    return (
        <>
            <div className="flex items-start gap-8">
                <div className="flex flex-col items-center gap-2 py-2">
                    {images.map((image, i) => (
                        <button
                            onClick={() => setSelectedImage(image)}
                            className={`border-2 rounded ${
                                selectedImage?.id === image.id ? 'border-blue-500 shadow-lg' : 'border-transparent hover:border-blue-500'
                            }`}
                            key={image.id}
                        >
                            <img src={image.thumb} alt={`Thumbnail of ${image.id}`} className="w-[50px]" />
                        </button>
                    ))}
                </div>

                <div className="carousel w-full">
                    <div className="carousel-item w-full">
                        {selectedImage && (
                            <img
                                src={selectedImage.large}
                                alt={`Selected image ${selectedImage.id}`}
                                className="w-full"
                            />
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

export default Carousel;
