import type { SVGAttributes } from 'react';

// A corujinha da Owly em formas simples, para funcionar de 16 px (aba) a 160 px.
// As cores são da marca e não mudam com o tema.
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 64 64"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="Owly"
            {...props}
        >
            <path d="M15 20 11 4l14 9Z" fill="#523CCF" />
            <path d="M49 20 53 4l-14 9Z" fill="#523CCF" />
            <path
                d="M8 31C8 16 19 9 32 9s24 7 24 22v13c0 11-11 17-24 17S8 55 8 44Z"
                fill="#523CCF"
            />
            <ellipse cx="32" cy="48" rx="13" ry="9.5" fill="#B9A4FB" />
            <circle cx="22" cy="30" r="11" fill="#EEE9FF" />
            <circle cx="42" cy="30" r="11" fill="#EEE9FF" />
            <circle cx="22" cy="30" r="7.5" fill="#F7B848" />
            <circle cx="42" cy="30" r="7.5" fill="#F7B848" />
            <circle cx="22" cy="30" r="5" fill="#141342" />
            <circle cx="42" cy="30" r="5" fill="#141342" />
            <circle cx="23.8" cy="28" r="1.6" fill="#fff" />
            <circle cx="43.8" cy="28" r="1.6" fill="#fff" />
            <path d="M29.5 38h5L32 42.5Z" fill="#FF9E36" />
        </svg>
    );
}
