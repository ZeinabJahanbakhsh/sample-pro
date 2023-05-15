create table activity_types
(
    id         smallint identity
        constraint PK_activity_types
            primary key,
    name       nvarchar(50),
    created_at datetime not null,
    updated_at datetime not null
)
go

