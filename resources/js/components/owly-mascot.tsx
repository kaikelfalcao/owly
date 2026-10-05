import type { ImgHTMLAttributes } from 'react';

/** A coruja inteira, para a tela de entrar e as telas vazias. */
export default function OwlyMascot({
    alt = '',
    className,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    return (
        <img
            src="/images/owly-coruja.png"
            alt={alt}
            draggable={false}
            className={className}
            {...props}
        />
    );
}
