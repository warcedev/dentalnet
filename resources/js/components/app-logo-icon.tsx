import type { SVGAttributes } from 'react';

/** Isotipo: un diente estilizado. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M7.5 2C4.9 2 3 4.1 3 7c0 2.1.7 3.6 1.4 5.3.6 1.4 1 3 1.3 5.1.3 2.4.9 4.6 2.4 4.6 1.3 0 1.7-1.6 2-3.4.3-1.6.6-3.1 1.9-3.1s1.6 1.5 1.9 3.1c.3 1.8.7 3.4 2 3.4 1.5 0 2.1-2.2 2.4-4.6.3-2.1.7-3.7 1.3-5.1C20.3 10.6 21 9.1 21 7c0-2.9-1.9-5-4.5-5-1.6 0-2.6.7-3.4 1.2-.4.3-.8.5-1.1.5s-.7-.2-1.1-.5C10.1 2.7 9.1 2 7.5 2Z" />
        </svg>
    );
}
