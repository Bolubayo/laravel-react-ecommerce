import ProductItem from '@/Components/App/ProductItem';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps, PaginationProps, Product } from '@/types';
import { Head } from '@inertiajs/react';

export default function Home({
    products
}: PageProps<{ products: PaginationProps<Product> }>) {

    return (
        <AuthenticatedLayout>
            <Head title="Welcome" />
            
            <div className="hero bg-gray-200 min-h-screen">
                <div className="hero-content text-center">
                    <div className="max-w-md">
                        <h1 className="text-5xl font-bold">Welcome to Daniel's Store</h1>
                        <p className="py-6">
                            Discover amazing products at unbeatable prices. Shop the latest trends, gadgets, and more — all in one place.
                        </p>
                        <button className="btn btn-primary">Shop Now</button>
                    </div>
                </div>
            </div>

            {products?.data?.length ? (
                <div className="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3 p-8">
                    {products.data.map(product => (
                        <ProductItem product={product} key={product.id} />
                    ))}
                </div>
            ) : (
                <div className="p-8 text-center text-gray-500 text-lg">
                    No products available at the moment. Please check back later!
                </div>
            )}
        </AuthenticatedLayout>
    );
}
