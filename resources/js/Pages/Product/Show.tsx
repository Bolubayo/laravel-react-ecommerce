import React, { useEffect, useMemo, useState } from 'react';
import {PageProps, Product, VariationTypeOption} from "@/types";
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage, router, Link } from '@inertiajs/react';
import Carousel from '@/Components/Core/Carousel';
import CurrencyFormatter from '@/Components/Core/CurrencyFormatter';
import { arraysAreEqual } from '@/helpers';

function Show({
    appName,
    product,
    variationOptions
}: PageProps<{
    product: Product,
    variationOptions: number[]
}>) {
    // console.log('product.images', product.images)
    // console.log(product, variationOptions)

    const form = useForm<{
        option_ids: Record<string, number>;
        quantity: number;
        price: number | null;
    }>({
        option_ids: {},
        quantity: 1,
        price: null
    })

    const {url} = usePage();
    
    const [selectedOptions, setSelectedOptions] =
        useState<Record<number, VariationTypeOption>>([]);
    
    const images = useMemo(() => {
        for (let typeId in selectedOptions) {
            const option = selectedOptions[typeId];
            if (option.images.length > 0) return option.images;
        }    
        return product.images;
    }, [product, selectedOptions]);

    const computedProduct = useMemo(() => {
        const selectedOptionIds = Object.values(selectedOptions)
            .map(op => op.id)
            .sort();
        
        for (let variation of product.variations) {
            const optionIds = variation
                .variation_type_option_ids.sort();
            if (arraysAreEqual(selectedOptionIds, optionIds)) {
                return {
                    price: variation.price,
                    quantity: variation.quantity === null ? Number.MAX_VALUE: variation.quantity,
                }
            }
        }
        return {
            price: product.price,
            quantity: product.quantity
        };
    }, [product, selectedOptions]);
    

    useEffect(() => {
        for (let type of product.variationTypes) {
            // console.log(variationOptions)
            const selectedOptionId: number = variationOptions[type.id];
            console.log(selectedOptionId, type.options)
            chooseOption(
                type.id,
                type.options.find(op => op.id == selectedOptionId) || type.options[0],
                false
            )
        }
    }, []);

    const getOptionIdsMap = (newOptions: object) => {
        return Object.fromEntries(
            Object.entries(newOptions).map(([a, b]) => 
            [a, b.id])
        )
    }   

    
    const chooseOption = (
        typeId: number,
        option: VariationTypeOption,
        updateRouter: boolean = true
    ) => {
        setSelectedOptions((prevSelectedOptions) => {
            const newOptions = {
                ...prevSelectedOptions,
                [typeId]: option
            }

            if (updateRouter) {
                router.get(url, {
                    options: getOptionIdsMap(newOptions)
                }, {
                    preserveScroll: true,
                    preserveState: true
                })
            }

            return newOptions
        })
    }

    const onQuantityChange = (ev: React.ChangeEvent<HTMLSelectElement>) => {
        form.setData('quantity', parseInt(ev.target.value))
    }

    const addToCart = () => {
        form.post(route('cart.store', product.id), {
            preserveScroll: true,
            preserveState: true,
            onError: (err) => {
                console.log(err)
            }
        })
    }

    const renderProductVariationTypes = () => {
        return product.variationTypes?.map(type => (
            <div key={type.id} className="mb-6">
                <label className="block font-semibold mb-2">{type.name}</label>
    
                {/* Image Type */}
                {type.type === 'Image' && (
                    <div className="flex gap-3">
                        {type.options.map(option => (
                            <div
                                key={option.id}
                                onClick={() => chooseOption(type.id, option)}
                                className={`p-1 rounded cursor-pointer border ${
                                    selectedOptions[type.id]?.id === option.id
                                        ? 'outline outline-4 outline-primary'
                                        : 'outline outline-1 outline-gray-300'
                                }`}
                            >
                                <img
                                    src={option.images?.[0]?.thumb}
                                    alt={option.name}
                                    className="w-12 h-12 object-cover rounded"
                                />
                            </div>
                        ))}
                    </div>
                )}
    
                {/* Radio Type */}
                {type.type === 'Radio' && (
                    <div className="flex gap-2 mt-2 flex-wrap">
                        {type.options.map(option => (
                            <button
                                key={option.id}
                                type="button"
                                onClick={() => chooseOption(type.id, option)}
                                className={`px-4 py-1 rounded border text-sm ${
                                    selectedOptions[type.id]?.id === option.id
                                        ? 'bg-blue-700 text-white border-blue-600'
                                        : 'bg-white border-gray-300 hover:bg-gray-100'
                                }`}
                            >
                                {option.name}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        ));
    };
    

    const renderAddToCartButton = () => {
        return (<div className="mb-8 flex gap-4">
            <select value={form.data.quantity}
                    onChange={onQuantityChange}
                    className="select select-bordered w-full">
                {Array.from({
                    length: Math.min(10, computedProduct.quantity)
                }).map((el, i) => (
                    <option value={i + 1} key={i + 1}>Quantity: {i + 1}</option>
                ))}
            </select>
            <button onClick={addToCart} className="btn btn-primary">Add to Cart</button>
            </div>
        )
    }

    useEffect(() => {
        const idsMap = Object.fromEntries(
            Object.entries(selectedOptions).map(([typeId, option]: [string, VariationTypeOption]) => [typeId, option.id])
        )
        console.log(idsMap)
        form.setData('option_ids', idsMap)
    }, [selectedOptions]);
  
    return (
        <AuthenticatedLayout>
            <Head>
                <title>{product.title}</title>
                <meta name="title" content={product.meta_title}/>
                <meta name="description" content={product.meta_description}/>
                <link rel="canonical" href={route('product.show', product.slug)} />
                            
                <meta property="og:title" content={product.title}/>
                <meta property="og:description" content={product.meta_description}/>
                <meta property="og:image" content={images[0]?.small}/>
                <meta property="og:url" content={route('product.show', product.slug)}/>
                <meta property="og:type" content="product"/>
                <meta property="og:site_name" content={appName}/>
            </Head> 
            
            <div className="container mx-auto p-8">
                <div className="grid gap-8 grid-cols-1 lg:grid-cols-12">
                    <div className="col-span-7">
                        <Carousel images={images}/>
                    </div>
                    <div className="col-span-5">
                        <h1 className="text-2xl">{product.title}</h1>

                        <p className={'mb-8'}>
                            by <Link href={route('vendor.profile', product.user.store_name)} className="hover::underline">
                                {product.user.name}
                            </Link>&nbsp;
                            in <Link href={route('product.byDepartment', product.department.slug)} className="hover::underline">{product.department.name}</Link>
                        </p>
                        
                        <div>
                            <div className="text-3xl font-semibold">
                                <CurrencyFormatter amount={computedProduct.price}/>
                            </div>
                        </div>

                        
                        {renderProductVariationTypes()}

                        {computedProduct.quantity != undefined && computedProduct.quantity < 10 &&
                        <div className="text-error my-4">
                            <span>Only {computedProduct.quantity} left</span>
                        </div>
                        }
                        {renderAddToCartButton()}

                        <b className="text-xl">About the Item</b>
                        <div className="wysiwyg-output"
                        dangerouslySetInnerHTML={{__html: product.description}}/>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

export default Show;