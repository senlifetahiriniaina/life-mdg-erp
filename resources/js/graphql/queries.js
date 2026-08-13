import gql from 'graphql-tag';

// CRM Queries
export const GET_CONTACTS = gql`
  query GetContacts($first: Int = 10, $after: String, $filter: ContactFilter) {
    contacts(first: $first, after: $after, filter: $filter) {
      edges {
        cursor
        node {
          id
          firstName
          lastName
          email
          phone
          jobTitle
          status
          account {
            id
            name
            industry
          }
          opportunities {
            id
            name
            stage
            amount
          }
          createdAt
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
`;

export const GET_CONTACT = gql`
  query GetContact($id: ID!) {
    contact(id: $id) {
      id
      firstName
      lastName
      email
      phone
      jobTitle
      status
      account {
        id
        name
        industry
        website
      }
      opportunities {
        id
        name
        stage
        amount
        probability
      }
      activities {
        id
        type
        description
      }
      createdAt
      updatedAt
    }
  }
`;

export const GET_ACCOUNTS = gql`
  query GetAccounts($first: Int = 10, $after: String) {
    accounts(first: $first, after: $after) {
      edges {
        cursor
        node {
          id
          name
          industry
          website
          phone
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
`;

export const GET_OPPORTUNITIES = gql`
  query GetOpportunities($first: Int = 10, $after: String, $stage: OpportunityStage) {
    opportunities(first: $first, after: $after, stage: $stage) {
      edges {
        cursor
        node {
          id
          name
          stage
          amount
          probability
          contact {
            id
            firstName
            lastName
          }
          account {
            id
            name
          }
          createdAt
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
`;

// HR Queries
export const GET_EMPLOYEES = gql`
  query GetEmployees($first: Int = 10, $after: String) {
    employees(first: $first, after: $after) {
      edges {
        cursor
        node {
          id
          firstName
          lastName
          email
          phone
          jobTitle
          startDate
          department {
            id
            name
          }
          createdAt
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
`;

export const GET_DEPARTMENTS = gql`
  query GetDepartments($first: Int = 10, $after: String) {
    departments(first: $first, after: $after) {
      edges {
        cursor
        node {
          id
          name
          manager
          budget
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
`;

// Inventory Queries
export const GET_PRODUCTS = gql`
  query GetProducts($first: Int = 10, $after: String) {
    products(first: $first, after: $after) {
      edges {
        cursor
        node {
          id
          name
          sku
          description
          price
          cost
          stock {
            id
            quantity
            warehouseLocation
          }
          createdAt
        }
      }
      pageInfo {
        hasNextPage
        endCursor
      }
    }
  }
`;

// Mutations
export const CREATE_CONTACT = gql`
  mutation CreateContact($input: CreateContactInput!) {
    createContact(input: $input) {
      id
      firstName
      lastName
      email
      phone
      jobTitle
      status
      account {
        id
        name
      }
      createdAt
    }
  }
`;

export const UPDATE_CONTACT = gql`
  mutation UpdateContact($id: ID!, $input: UpdateContactInput!) {
    updateContact(id: $id, input: $input) {
      id
      firstName
      lastName
      email
      phone
      jobTitle
      status
      updatedAt
    }
  }
`;

export const DELETE_CONTACT = gql`
  mutation DeleteContact($id: ID!) {
    deleteContact(id: $id)
  }
`;

export const CREATE_ACCOUNT = gql`
  mutation CreateAccount($input: CreateAccountInput!) {
    createAccount(input: $input) {
      id
      name
      industry
      website
      phone
      createdAt
    }
  }
`;

export const UPDATE_ACCOUNT = gql`
  mutation UpdateAccount($id: ID!, $input: UpdateAccountInput!) {
    updateAccount(id: $id, input: $input) {
      id
      name
      industry
      website
      phone
      updatedAt
    }
  }
`;

export const DELETE_ACCOUNT = gql`
  mutation DeleteAccount($id: ID!) {
    deleteAccount(id: $id)
  }
`;
