// Example usage of generated types in React/TypeScript
//@ts-nocheck
import type { User, Event, Product } from '@/types/generated';

// Example 1: Using the User type in a component
interface UserCardProps {
    user: User;
}

const UserCard: React.FC<UserCardProps> = ({ user }) => {
    return (
        <div className="user-card">
            <h2>{user.name}</h2>
            <p>{user.email}</p>
            {user.bio && <p className="bio">{user.bio}</p>}
            
            {user.is_verified && <span className="badge verified">Verified</span>}
            {user.is_premium && <span className="badge premium">Premium</span>}
        </div>
    );
};

// Example 2: Using Event type in a component
interface EventCardProps {
    event: Event;
}

const EventCard: React.FC<EventCardProps> = ({ event }) => {
    return (
        <div className="event-card">
            <h2>{event.title}</h2>
            <p>{event.description}</p>
            <div className="event-meta">
                <span>Date: {event.start_date}</span>
                <span>Capacity: {event.capacity}</span>
                {event.is_featured && <span className="badge featured">Featured</span>}
            </div>
        </div>
    );
};

// Example 3: Using types in API calls
const fetchUsers = async (): Promise<User[]> => {
    const response = await fetch('/api/users');
    const data = await response.json();

    // Full type safety on the returned data
    return data as User[];
};
// Example 4: Using types with form data
interface CreateEventForm {
    title: string;
    description?: string;
    start_date: string;
    capacity: number;
}

const createEvent = async (formData: CreateEventForm): Promise<Event> => {
    const response = await fetch('/api/events', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData),
    });
    
    return response.json() as Promise<Event>;
};

// Example 5: Using nested types
interface ProductWithReviews extends Product {
    reviews: ProductReview[];
    average_rating: number;
}

const ProductDisplay: React.FC<{ product: ProductWithReviews }> = ({ product }) => {
    return (
        <div className="product-display">
            <h1>{product.name}</h1>
            <p>${product.price}</p>
            <div className="rating">
                Rating: {product.average_rating}/5 ({product.reviews.length} reviews)
            </div>
        </div>
    );
};

export { UserCard, EventCard, fetchUsers, createEvent, ProductDisplay };