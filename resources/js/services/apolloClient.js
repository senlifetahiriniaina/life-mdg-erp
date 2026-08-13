import { ApolloClient, InMemoryCache, HttpLink, ApolloLink } from '@apollo/client';
import { onError } from '@apollo/client/link/error';

const errorLink = onError(({ graphQLErrors, networkError }) => {
  if (graphQLErrors) {
    graphQLErrors.forEach(({ message, locations, path }) => {
      console.error(`[GraphQL error]: ${message}`, { locations, path });
    });
  }
  if (networkError) {
    console.error(`[Network error]: ${networkError}`);
  }
});

const httpLink = new HttpLink({
  uri: '/graphql',
  credentials: 'include',
});

const authLink = new ApolloLink((operation, forward) => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content;
  if (token) {
    operation.setContext({
      headers: {
        'X-CSRF-TOKEN': token,
      },
    });
  }
  return forward(operation);
});

export const apolloClient = new ApolloClient({
  ssrMode: typeof window === 'undefined',
  link: ApolloLink.from([errorLink, authLink, httpLink]),
  cache: new InMemoryCache(),
  defaultOptions: {
    watchQuery: {
      fetchPolicy: 'cache-and-network',
    },
    query: {
      fetchPolicy: 'cache-and-network',
    },
  },
});
