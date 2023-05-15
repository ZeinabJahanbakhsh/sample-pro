create table cities
(
    id         smallint identity
        constraint PK_cities
            primary key,
    name       nvarchar(50),
    created_at datetime not null,
    updated_at datetime not null
)
go

